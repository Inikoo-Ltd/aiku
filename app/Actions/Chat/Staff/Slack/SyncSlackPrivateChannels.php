<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackSyncRun;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * A private channel comes whole when a member adds the aiku bot to it (INI-431, decided 26 Nov 2026 under INI-427): with the bot
 * token Slack lists only the private channels the bot is in, so nothing else is ever read. Each comes with its members and,
 * with --history, its messages. A channel the bot was removed from keeps what was brought and gets nothing new.
 * Stored rows are read by the channel's members only; kept() never includes them.
 */
class SyncSlackPrivateChannels
{
    use AsAction;
    use WithSlack;
    use WithSlackChannelHistory;

    public const string COMMAND = 'slack:sync-private-channels';

    public string $commandSignature = 'slack:sync-private-channels
        {--history : Bring the past messages of the private channels the aiku app is in}
        {--channel= : Only this Slack channel id}';

    public string $commandDescription = 'Keep the private Slack channels the aiku app was added to, with their members and messages';

    /**
     * @return array{channels: int, members: int, messages: int, added: int, failed: list<string>}
     */
    public function handle(Group $group, bool $history = false, ?string $onlySlackId = null): array
    {
        $client      = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $totals      = ['channels' => 0, 'members' => 0, 'messages' => 0, 'failed' => []];
        $this->added = 0;
        $listed      = [];
        $cursor      = null;

        do {
            $page = $this->slackCall($client, 'conversations.list', array_filter(['types' => 'private_channel', 'limit' => 200, 'cursor' => $cursor]));
            foreach (Arr::get($page, 'channels', []) as $slackChannel) {
                $slackId = (string) Arr::get($slackChannel, 'id');
                if (!Arr::get($slackChannel, 'is_private') || ($onlySlackId && $slackId !== $onlySlackId)) {
                    continue;
                }
                $listed[] = $slackId;
                if (SlackChannel::where('slack_id', $slackId)->where('is_ignored', true)->exists()) {
                    continue;
                }
                $channel = SlackChannel::updateOrCreate(['slack_id' => $slackId], [
                    'group_id'    => $group->id,
                    'name'        => Arr::get($slackChannel, 'name'),
                    'is_archived' => (bool) Arr::get($slackChannel, 'is_archived'),
                    'is_member'   => true,
                    'is_public'   => false,
                    'is_private'  => true,
                    'data'        => $slackChannel,
                ]);
                $totals['channels']++;

                try {
                    $totals['members'] += $this->syncMembers($client, $channel);
                    if ($history) {
                        $totals['messages'] += $this->syncHistory($client, $channel);
                    }
                } catch (Throwable $exception) {
                    $totals['failed'][] = "$channel->name ($channel->slack_id): {$exception->getMessage()}";
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        if (!$onlySlackId) {
            SlackChannel::where('is_private', true)->where('is_member', true)->whereNotIn('slack_id', $listed)->update(['is_member' => false]);
        }

        return $totals + ['added' => $this->added];
    }

    /**
     * Who may read a private channel comes from this list, so a list that could not be read in full changes nothing.
     */
    private function syncMembers(PendingRequest $client, SlackChannel $channel): int
    {
        $slackUserIds = [];
        $cursor       = null;
        do {
            $page         = $this->slackCall($client, 'conversations.members', array_filter(['channel' => $channel->slack_id, 'limit' => 1000, 'cursor' => $cursor]));
            $slackUserIds = array_merge($slackUserIds, Arr::get($page, 'members', []));
            $cursor       = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);
        $slackUserIds = array_values(array_unique($slackUserIds));

        DB::transaction(function () use ($channel, $slackUserIds) {
            $channel->members()->whereNotIn('slack_user_id', $slackUserIds)->delete();
            foreach ($slackUserIds as $slackUserId) {
                $channel->members()->firstOrCreate(['slack_user_id' => $slackUserId]);
            }
        });

        return count($slackUserIds);
    }

    protected function isStillKept(SlackChannel $channel): bool
    {
        return SlackChannel::keptPrivate()->whereKey($channel->id)->where('is_member', true)->exists();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $onlySlackId = $command->option('channel') ?: null;
        $run         = fn () => $this->handle(Group::firstOrFail(), (bool) $command->option('history'), $onlySlackId);
        $totals      = $onlySlackId ? $run() : SlackSyncRun::record(self::COMMAND, $run);
        $command->info("Private channels: {$totals['channels']}, members: {$totals['members']}, messages read: {$totals['messages']}, new: {$totals['added']}");
        foreach ($totals['failed'] as $failure) {
            $command->error($failure);
        }

        return $totals['failed'] ? 1 : 0;
    }
}
