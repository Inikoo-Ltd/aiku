<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelMember;
use App\Models\Chat\SlackSyncRun;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * Replaces the member list of each kept public channel the aiku app is in with the one Slack gives, and drops the
 * members of channels no longer kept. A channel whose list could not be read keeps its old list.
 */
class SyncSlackChannelMembers
{
    use AsAction;
    use WithSlack;

    public string $commandSignature = 'slack:sync-channel-members';

    public string $commandDescription = 'Keep who is in each public Slack channel kept in aiku';

    /**
     * @return array{channels: int, members: int, failed: list<string>}
     */
    public function handle(): array
    {
        $client  = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
        $totals  = ['channels' => 0, 'members' => 0, 'failed' => []];

        SlackChannelMember::whereNotIn('slack_channel_id', SlackChannel::kept()->select('id'))->whereNotIn('slack_channel_id', SlackChannel::keptPrivate()->select('id'))->delete();

        foreach (SlackChannel::kept()->where('is_member', true)->get() as $channel) {
            try {
                $slackUserIds = [];
                $cursor       = null;
                do {
                    $page         = $this->slackCall($client, 'conversations.members', array_filter(['channel' => $channel->slack_id, 'limit' => 1000, 'cursor' => $cursor]));
                    $slackUserIds = array_merge($slackUserIds, Arr::get($page, 'members', []));
                    $cursor       = Arr::get($page, 'response_metadata.next_cursor');
                } while ($cursor);
            } catch (Throwable $exception) {
                $totals['failed'][] = "$channel->name ($channel->slack_id): {$exception->getMessage()}";

                continue;
            }

            DB::transaction(function () use ($channel, $slackUserIds) {
                $channel->members()->whereNotIn('slack_user_id', $slackUserIds)->delete();
                foreach (array_unique($slackUserIds) as $slackUserId) {
                    $channel->members()->firstOrCreate(['slack_user_id' => $slackUserId]);
                }
            });
            $totals['channels']++;
            $totals['members'] += count(array_unique($slackUserIds));
        }

        return $totals;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $totals = SlackSyncRun::record($this->commandSignature, fn () => $this->handle());
        $command->info("Channels: {$totals['channels']}, members: {$totals['members']}");
        foreach ($totals['failed'] as $failure) {
            $command->error($failure);
        }

        return $totals['failed'] ? 1 : 0;
    }
}
