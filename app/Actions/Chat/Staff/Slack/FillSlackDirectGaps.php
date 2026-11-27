<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteClient;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteException;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * When a person opts in, their own token fills the gaps already left for them in the DMs others brought (INI-431), and completes
 * those conversations' member lists. Only this person's messages are kept: the others' text in Slack's answer is dropped in memory.
 * The job carries the user id only. BringSlackDirectHistory runs it at the start of each slice of a person's walk (INI-433).
 */
class FillSlackDirectGaps
{
    use AsAction;

    public int $jobTries = 1;

    /**
     * @return array{channels: int, filled: int, complete: bool}
     */
    public function handle(User $user): array
    {
        $totals      = ['channels' => 0, 'filled' => 0, 'complete' => false];
        $slackPerson = SlackChannel::privateReader($user);
        $client      = $slackPerson ? SlackWriteClient::for($user) : null;
        if (!$client || !StoreSlackDirectMessage::hasOptedIn($slackPerson->slack_id)) {
            return $totals;
        }

        $totals['complete'] = true;
        foreach (SlackChannel::directVisibleTo($user)->get() as $channel) {
            try {
                $members = Arr::get($client->call('conversations.members', ['channel' => $channel->slack_id, 'limit' => 100]), 'members', []);
                StoreSlackDirectMessage::make()->addMembers($channel, array_filter((array) $members, 'is_string'));

                $gaps = SlackMessage::where('slack_channel_id', $channel->id)->where('slack_user_id', $slackPerson->slack_id)->whereNull('data')->get(['ts', 'thread_ts']);
                if ($gaps->isEmpty()) {
                    continue;
                }
                $totals['channels']++;
                $wanted = $gaps->pluck('ts')->flip()->all();
                $pages  = [['conversations.history', ['oldest' => $gaps->min('ts'), 'latest' => $gaps->max('ts')]]];
                foreach ($gaps->pluck('thread_ts')->filter()->unique() as $threadTs) {
                    $pages[] = ['conversations.replies', ['ts' => $threadTs]];
                }
                foreach ($pages as [$method, $range]) {
                    $cursor = null;
                    do {
                        $page = $client->call($method, array_filter([...$range, 'channel' => $channel->slack_id, 'inclusive' => true, 'limit' => 200, 'cursor' => $cursor]));
                        foreach ((array) Arr::get($page, 'messages', []) as $message) {
                            $ts = (string) Arr::get(is_array($message) ? $message : [], 'ts');
                            if (isset($wanted[$ts]) && Arr::get($message, 'user') === $slackPerson->slack_id && StoreSlackDirectMessage::make()->store($channel, $message)) {
                                unset($wanted[$ts]);
                                $totals['filled']++;
                            }
                        }
                        $cursor = Arr::get($page, 'response_metadata.next_cursor') ?: null;
                    } while ($cursor);
                }
            } catch (SlackWriteException $exception) {
                $totals['complete'] = false;
                Log::warning('Slack DM gaps not filled', ['user_id' => $user->id, 'channel' => $channel->slack_id, 'error' => $exception->slackError]);
                if ($exception->slackError === 'ratelimited' || !SlackWriteClient::for($user)) {
                    break;
                }
            } catch (Throwable $exception) {
                $totals['complete'] = false;
                Log::warning('Slack DM gaps not filled', ['user_id' => $user->id, 'channel' => $channel->slack_id, 'error' => $exception::class]);
            }
        }

        return $totals;
    }

    public function asJob(int $userId): void
    {
        if ($user = User::find($userId)) {
            $this->handle($user);
        }
    }
}
