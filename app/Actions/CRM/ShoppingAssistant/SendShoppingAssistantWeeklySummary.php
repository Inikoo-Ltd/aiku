<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use App\Notifications\ShoppingAssistantWeeklySummaryNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Emails the people picked in Sysadmin > Settings > AI shopping assistant (Weekly summary to) the
 * group's figures of last Monday to Sunday against the week before, the top challenges and the
 * searches with no results, with a link to the WebOps report. Nobody picked: nothing is sent.
 * Only those who can open that report (group admins, group webmasters) get it: the email carries
 * the group's revenue and AI cost.
 *
 * Quotes stay in the app: an email cannot be wiped when the retention period ends.
 */
class SendShoppingAssistantWeeklySummary
{
    use AsAction;
    use WithShopPpcPermissions;

    public string $commandSignature = 'shopping-assistant:weekly-summary {--dry-run : Print the summary instead of sending it}';

    public string $commandDescription = 'Email the AI shopping assistant weekly summary';

    /**
     * @return array<string, mixed>
     */
    public function summary(Group $group, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $websiteIds = Website::where('group_id', $group->id)->pluck('id')->all();
        $filters    = [
            'website_ids'        => $websiteIds,
            'search_website_ids' => $websiteIds,
            'language'           => null,
            'entry_point'        => null,
            'shop_type'          => null,
            'country'            => null,
        ];

        $report = GetShoppingAssistantReport::make()->handle($filters, $from, $to, $group->currency->code, false);

        return [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'currency'      => $group->currency->code,
            'cost_currency' => $report['cost_currency'],
            'kpis'          => Arr::only($report['kpis'], ['conversations', 'customers', 'orders', 'revenue', 'strict_revenue', 'ai_cost']),
            'feedback'      => $report['feedback']['current'],
            'hand_overs'    => $report['general']['hand_overs'] ?? ['value' => 0, 'previous' => 0],
            'challenges'    => $report['challenges']['top'],
            'zero_searches' => array_slice($report['search']['top_zero_queries'], 0, 10),
            'url'           => route('grp.websites.shopping_assistant.show', ['from' => $from->toDateString(), 'to' => $to->toDateString()]),
        ];
    }

    public function handle(Group $group, ?CarbonImmutable $today = null): int
    {
        $recipients = User::where('group_id', $group->id)
            ->where('status', true)
            ->whereIn('id', Arr::get($group->settings, 'shopping_assistant.weekly_summary_user_ids', []) ?: [0])
            ->get()
            ->filter(fn (User $user) => $this->canViewGroupShoppingAssistant($user));

        if ($recipients->isEmpty()) {
            return 0;
        }

        [$from, $to] = $this->lastWeek($today);
        $summary     = $this->summary($group, $from, $to);

        $recipients->each(fn (User $user) => rescue(fn () => $user->notify(new ShoppingAssistantWeeklySummaryNotification($summary))));

        return $recipients->count();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function lastWeek(?CarbonImmutable $today = null): array
    {
        $monday = ($today ?? CarbonImmutable::today(config('app.timezone')))->startOfWeek()->subWeek();

        return [$monday, $monday->addDays(6)];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        foreach (Group::all() as $group) {
            if ($command->option('dry-run')) {
                [$from, $to] = $this->lastWeek();
                $command->line(json_encode($this->summary($group, $from, $to), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                continue;
            }

            $command->info("$group->slug: sent to {$this->handle($group)}");
        }

        return 0;
    }
}
