<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat;

use App\Helpers\SlackNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Facades\Cache;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The inbox already shows the unclaimed queue to whoever has it open. This is for the hours
 * when nobody has: the conversations that went unanswered last week were not slow, they sat in
 * a queue nobody watching could answer, and nothing ever said so out loud.
 */
class AlertUnclaimedChatSessions
{
    use AsAction;
    use WithUnclaimedChatSessions;

    public string $commandSignature = 'chat:alert-unclaimed {--r|dry-run : Report what would be sent, send nothing}';

    public string $commandDescription = 'Report conversations nobody has claimed past the time agreed for their channel';

    private const SIGNATURE_KEY = 'chat:unclaimed:last-alerted';

    /**
     * @return array{total: int, by_shop: array<string, int>, oldest_minutes: int|null, sent: bool}
     */
    public function handle(bool $dryRun = false): array
    {
        $rows = $this->unclaimedChatSessions()->with('shop')->get()
            ->map(fn ($session) => [
                'id'      => 'c'.$session->id,
                'shop'    => $session->shop?->name ?? '-',
                'waiting' => Carbon::parse($session->last_visitor_message_at ?? $session->created_at),
            ])
            ->concat(
                $this->unclaimedMetaChatSessions()->with('shop')->get()
                    ->map(fn ($session) => [
                        'id'      => 'm'.$session->id,
                        'shop'    => $session->shop?->name ?? '-',
                        'waiting' => Carbon::parse($session->last_visitor_message_at),
                    ])
            );

        if ($rows->isEmpty()) {
            return ['total' => 0, 'by_shop' => [], 'oldest_minutes' => null, 'sent' => false];
        }

        $report = [
            'total'          => $rows->count(),
            'by_shop'        => $rows->countBy('shop')->sortDesc()->all(),
            'oldest_minutes' => (int) $rows->sortBy('waiting')->first()['waiting']?->diffInMinutes(now()),
            'sent'           => false,
        ];

        // One message per change, not one per sweep: the queue is re-read every few minutes and
        // a backlog that takes an hour to clear would otherwise repeat itself twenty times.
        $signature = $rows->pluck('id')->sort()->implode(',');

        if ($dryRun || Cache::get(self::SIGNATURE_KEY) === $signature) {
            return $report;
        }

        Cache::put(self::SIGNATURE_KEY, $signature, now()->addDay());

        $report['sent'] = $this->sendToSlack($report);

        return $report;
    }

    private function sendToSlack(array $report): bool
    {
        $channel = config('chat.unclaimed.slack_channel');
        $token   = config('services.slack.notifications.bot_user_oauth_token');

        if (blank($channel) || blank($token)) {
            return false;
        }

        $shops = collect($report['by_shop'])->map(fn ($count, $shop) => "$shop: $count")->implode("\n");

        $message = (new SlackMessage())
            ->headerBlock(':rotating_light: '.$report['total'].' conversations nobody has taken')
            ->sectionBlock(fn ($block) => $block->text(
                "Longest waiting: {$report['oldest_minutes']} minutes\n\n$shops"
            )->markdown());

        (new AnonymousNotifiable())->route('slack', $channel)->notify(new SlackNotification($message));

        return true;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $report = $this->handle((bool) $command->option('dry-run'));

        foreach ($report['by_shop'] as $shop => $count) {
            $command->line("$shop: $count");
        }

        $command->info("{$report['total']} unclaimed, oldest waiting {$report['oldest_minutes']} minutes"
            .($report['sent'] ? ', reported to Slack' : ''));

        return 0;
    }
}
