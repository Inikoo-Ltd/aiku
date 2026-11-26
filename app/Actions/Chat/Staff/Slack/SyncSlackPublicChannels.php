<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackSyncRun;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Slack sends new messages only from channels the aiku app is in, and gives the history of those channels alone.
 * Joining a channel shows a notice in it, so it happens only when asked for. A channel Slack no longer lists as
 * public has turned private or gone, and is marked so nothing more of it is kept.
 */
class SyncSlackPublicChannels
{
    use AsAction;
    use WithSlack;
    use WithSlackChannelHistory;

    public string $commandSignature = 'slack:sync-public-channels
        {--join : Join the public channels the aiku app is not in yet; Slack shows a notice in each of them}
        {--history : Bring the past messages of the channels the aiku app is in}
        {--catch-up : With --history, only re-read the channels whose past messages are already complete}';

    public string $commandDescription = 'List the public Slack channels and keep their messages in aiku';

    /**
     * @return array{channels: int, joined: int, messages: int, added: int}
     */
    public function handle(Group $group, bool $join = false, bool $history = false, bool $catchUpOnly = false): array
    {
        $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $totals = ['channels' => 0, 'joined' => 0, 'messages' => 0];
        $this->added = 0;
        $cursor = null;
        $listed = [];

        do {
            $page = $this->slackCall($client, 'conversations.list', array_filter(['types' => 'public_channel', 'limit' => 200, 'cursor' => $cursor]));
            foreach (Arr::get($page, 'channels', []) as $slackChannel) {
                $channel = SlackChannel::updateOrCreate(['slack_id' => $slackChannel['id']], [
                    'group_id'    => $group->id,
                    'name'        => Arr::get($slackChannel, 'name'),
                    'is_archived' => (bool) Arr::get($slackChannel, 'is_archived'),
                    'is_member'   => (bool) Arr::get($slackChannel, 'is_member'),
                    'data'        => $slackChannel,
                ]);
                $totals['channels']++;
                $listed[] = $channel->slack_id;

                if ($join && $channel->is_public && !$channel->is_ignored && !$channel->is_member && !$channel->is_archived) {
                    $this->slackCall($client, 'conversations.join', ['channel' => $channel->slack_id], 'post');
                    $channel->update(['is_member' => true]);
                    $totals['joined']++;
                }
                if ($history && $channel->is_public && !$channel->is_ignored && $channel->is_member && (!$catchUpOnly || $channel->history_synced_at)) {
                    $totals['messages'] += $this->syncHistory($client, $channel);
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        if ($listed) {
            SlackChannel::where('is_public', true)->whereNull('deleted_in_slack_at')->whereNotIn('slack_id', $listed)->update(['is_public' => false]);
        }

        return $totals + ['added' => $this->added];
    }

    protected function isStillKept(SlackChannel $channel): bool
    {
        return SlackChannel::whereKey($channel->id)->where('is_public', true)->exists();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $run = SlackSyncRun::create(['command' => 'slack:sync-public-channels', 'started_at' => now()]);
        try {
            $totals = $this->handle(Group::firstOrFail(), (bool) $command->option('join'), (bool) $command->option('history'), (bool) $command->option('catch-up'));
        } catch (Throwable $exception) {
            $run->update(['finished_at' => now(), 'error' => $exception->getMessage()]);

            throw $exception;
        }
        $run->update(['finished_at' => now(), 'channels' => $totals['channels'], 'messages_read' => $totals['messages'], 'messages_added' => $totals['added']]);
        $command->info("Public channels: {$totals['channels']}, joined now: {$totals['joined']}, messages read: {$totals['messages']}, new: {$totals['added']}");

        return 0;
    }
}
