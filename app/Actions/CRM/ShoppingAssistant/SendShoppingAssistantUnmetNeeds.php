<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantChallengeTypeEnum;
use App\Helpers\SlackNotification;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Notifications\ShoppingAssistantUnmetNeedsNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Every Monday tells the purchasing people picked in Sysadmin > Settings > AI shopping assistant
 * (Products customers asked for, by email, and the Slack channel set there) what customers asked
 * the assistant for last week that we do not sell or had out of stock, from the nightly challenge
 * tagging: the topic, how many conversations and which shops. Merlin tells the customer it passes
 * these on, and this is how. Topics only, never customer quotes, as the weekly summary.
 */
class SendShoppingAssistantUnmetNeeds
{
    use AsAction;

    public const array TYPES = [ShoppingAssistantChallengeTypeEnum::UNMET_NEED, ShoppingAssistantChallengeTypeEnum::OUT_OF_STOCK];

    public const int MAX_PER_TYPE = 40;

    public string $commandSignature = 'shopping-assistant:unmet-needs {--dry-run : Print the list instead of sending it}';

    public string $commandDescription = 'Send purchasing the products customers asked the AI shopping assistant for that we do not have';

    /**
     * @return array{from: string, to: string, needs: array<string, array<int, array{topic: string, conversations: int, shops: string}>>}
     */
    public function needs(Group $group, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $needs = [];
        foreach (self::TYPES as $type) {
            $needs[$type->value] = DB::table('shopping_assistant_challenges as challenges')
                ->join('shopping_assistant_conversations as conversations', 'conversations.id', '=', 'challenges.shopping_assistant_conversation_id')
                ->join('shops', 'shops.id', '=', 'conversations.shop_id')
                ->where('conversations.group_id', $group->id)
                ->whereNull('conversations.wiped_at')
                ->where('conversations.created_at', '>=', $from->startOfDay())
                ->where('conversations.created_at', '<', $to->addDay()->startOfDay())
                ->where('challenges.type', $type->value)
                ->where('challenges.topic', '<>', '')
                ->groupBy('challenges.topic')
                ->orderByRaw('count(distinct challenges.shopping_assistant_conversation_id) desc, challenges.topic')
                ->limit(self::MAX_PER_TYPE)
                ->selectRaw("challenges.topic, count(distinct challenges.shopping_assistant_conversation_id) as conversations, string_agg(distinct shops.name, ', ') as shops")
                ->get()
                ->map(fn ($row) => ['topic' => (string) $row->topic, 'conversations' => (int) $row->conversations, 'shops' => (string) $row->shops])
                ->all();
        }

        return ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'needs' => $needs];
    }

    /**
     * @return array{emailed: int, slack: bool}
     */
    public function handle(Group $group, ?CarbonImmutable $today = null): array
    {
        [$from, $to] = SendShoppingAssistantWeeklySummary::make()->lastWeek($today);
        $needs       = $this->needs($group, $from, $to);
        $sent        = ['emailed' => 0, 'slack' => false];

        if (!array_filter($needs['needs'])) {
            return $sent;
        }

        $notification = new ShoppingAssistantUnmetNeedsNotification($needs);
        User::where('group_id', $group->id)
            ->where('status', true)
            ->whereIn('id', Arr::get($group->settings, 'shopping_assistant.unmet_needs_user_ids', []) ?: [0])
            ->get()
            ->each(function (User $user) use ($notification, &$sent) {
                rescue(function () use ($user, $notification, &$sent) {
                    $user->notify($notification);
                    $sent['emailed']++;
                });
            });

        $channel = trim((string) Arr::get($group->settings, 'shopping_assistant.unmet_needs_slack_channel', ''));
        if ($channel !== '' && filled(config('services.slack.notifications.bot_user_oauth_token'))) {
            try {
                (new AnonymousNotifiable())->route('slack', $channel)->notify(new SlackNotification($notification->toSlackMessage()));
                $sent['slack'] = true;
            } catch (Throwable $exception) {
                Log::warning('Shopping assistant unmet needs Slack post failed: '.$exception->getMessage());
            }
        }

        return $sent;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        foreach (Group::all() as $group) {
            if ($command->option('dry-run')) {
                [$from, $to] = SendShoppingAssistantWeeklySummary::make()->lastWeek();
                $command->line(json_encode($this->needs($group, $from, $to), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                continue;
            }

            $sent = $this->handle($group);
            $command->info("$group->slug: emailed {$sent['emailed']}, Slack ".($sent['slack'] ? 'posted' : 'not posted'));
        }

        return 0;
    }
}
