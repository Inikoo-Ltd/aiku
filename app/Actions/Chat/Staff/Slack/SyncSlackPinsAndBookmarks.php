<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Pins are kept current by Slack's events and re-read here; Slack sends no events for bookmarks, so this is how they stay current.
 */
class SyncSlackPinsAndBookmarks
{
    use AsAction;
    use WithSlack;

    public string $commandSignature = 'slack:sync-pins-and-bookmarks';

    public string $commandDescription = 'Keep the pinned messages and the bookmarks of the public Slack channels the aiku app is in';

    /**
     * @return array{channels: int, pinned: int, bookmarks: int}
     */
    public function handle(): array
    {
        $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $totals = ['channels' => 0, 'pinned' => 0, 'bookmarks' => 0];

        $channels = SlackChannel::where('is_public', true)->where('is_ignored', false)->where('is_member', true)->whereNull('deleted_in_slack_at')->get();
        $failure = null;
        foreach ($channels as $channel) {
            foreach (['pinned' => 'syncPins', 'bookmarks' => 'syncBookmarks'] as $total => $sync) {
                try {
                    $totals[$total] += $this->$sync($client, $channel);
                } catch (Throwable $exception) {
                    $failure ??= $exception;
                }
            }
            $totals['channels']++;
        }
        if ($failure) {
            throw $failure;
        }

        return $totals;
    }

    private function syncPins(PendingRequest $client, SlackChannel $channel): int
    {
        $pinnedIds = [];
        foreach ((array) Arr::get($this->slackCall($client, 'pins.list', ['channel' => $channel->slack_id]), 'items', []) as $item) {
            if ($message = StoreSlackPin::run($channel, (array) $item, true)) {
                $pinnedIds[] = $message->id;
            }
        }
        $channel->messages()->whereNotNull('pinned_at')->whereNotIn('id', $pinnedIds)->update(['pinned_at' => null, 'pinned_by_slack_user_id' => null]);

        return count($pinnedIds);
    }

    private function syncBookmarks(PendingRequest $client, SlackChannel $channel): int
    {
        $listed = [];
        foreach ((array) Arr::get($this->slackCall($client, 'bookmarks.list', ['channel_id' => $channel->slack_id], 'post'), 'bookmarks', []) as $bookmark) {
            $channel->bookmarks()->updateOrCreate(['slack_id' => Arr::get($bookmark, 'id')], [
                'title'               => Arr::get($bookmark, 'title'),
                'link'                => Arr::get($bookmark, 'link'),
                'emoji'               => Arr::get($bookmark, 'emoji'),
                'position'            => Arr::get($bookmark, 'rank'),
                'data'                => $bookmark,
                'removed_in_slack_at' => null,
            ]);
            $listed[] = Arr::get($bookmark, 'id');
        }
        $channel->bookmarks()->whereNull('removed_in_slack_at')->whereNotIn('slack_id', $listed)->update(['removed_in_slack_at' => now()]);

        return count($listed);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $totals = $this->handle();
        $command->info("Channels: {$totals['channels']}, pinned messages kept: {$totals['pinned']}, bookmarks: {$totals['bookmarks']}");

        return 0;
    }
}
