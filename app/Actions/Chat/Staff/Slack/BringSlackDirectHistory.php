<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackSyncRun;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Brings a person's earlier DMs and group DMs (INI-433) with their own token, once they opted in to private conversations:
 * users.conversations, then each conversation's history and threads, stored through StoreSlackDirectMessage::store, so each
 * author keeps only their own words and everyone else becomes a gap. Private channels come through SyncSlackPrivateChannels.
 * One person at a time across every server (a Postgres advisory lock; a job that finds it taken does nothing, as the running one
 * starts the next person when it finishes). A run stops after RUN_SECONDS and queues itself again; a failed or stopped run hands
 * over to the next person after it, and the nightly command starts again from the first person still waiting. Each run first
 * fills the person's gaps, and the walk is finished only once that pass completed. The job carries the user id only.
 */
class BringSlackDirectHistory
{
    use AsAction;
    use WithSlack;
    use WithSlackChannelHistory;

    public const string COMMAND = 'slack:bring-direct-history';

    private const int LOCK_KEY = 433_000_433;

    private const int RUN_SECONDS = 45 * 60;

    public string $commandSignature = 'slack:bring-direct-history';

    public string $commandDescription = 'Start or resume the walk over the earlier Slack DMs and group DMs of the people who opted in';

    public int $jobTries = 1;

    private ?SlackConnection $connection = null;

    private ?User $bringer = null;

    /**
     * @return array{conversations: int, done: int, finished: bool, timed_out: bool, failed: list<string>}
     */
    public function handle(User $user, ?int $runSeconds = null): array
    {
        $totals = ['conversations' => 0, 'done' => 0, 'finished' => false, 'timed_out' => false, 'failed' => []];
        if (!DB::selectOne('select pg_try_advisory_lock(?) as locked', [self::LOCK_KEY], false)->locked) {
            return $totals;
        }
        try {
            $totals = $this->bring($user, now()->addSeconds($runSeconds ?? self::RUN_SECONDS), $totals);
        } finally {
            DB::selectOne('select pg_advisory_unlock(?)', [self::LOCK_KEY], false);
        }

        if ($totals['finished']) {
            self::startNext();
        } elseif ($totals['timed_out']) {
            self::dispatch($user->id);
        } else {
            if ($totals['failed']) {
                Log::warning('Slack direct history stopped', ['user_id' => $user->id, 'errors' => $totals['failed']]);
            }
            self::startNext(SlackConnection::main()->where('user_id', $user->id)->value('id'));
        }

        return $totals;
    }

    /**
     * @param  array{conversations: int, done: int, finished: bool, timed_out: bool, failed: list<string>}  $totals
     * @return array{conversations: int, done: int, finished: bool, timed_out: bool, failed: list<string>}
     */
    private function bring(User $user, \Illuminate\Support\Carbon $stopAt, array $totals): array
    {
        $this->connection = SlackConnection::main()->where('user_id', $user->id)->first();
        $this->bringer    = $user;
        $slackPerson      = SlackChannel::privateReader($user);
        $client           = $this->connection ? SyncSlackPrivateChannels::clientFor($this->connection) : null;
        if (!$client || !$slackPerson || $slackPerson->slack_id !== $this->connection->slack_user_id || !SyncSlackPrivateChannels::stillOptedIn($user->id)) {
            $this->connection = null;

            return $totals;
        }

        try {
            $gapsFilled = FillSlackDirectGaps::run($user)['complete'];
            if (!$this->connection->direct_history_started_at) {
                $this->connection->forceFill(['direct_history_started_at' => now()])->save();
            }
            $channels = array_values(array_filter(array_map(fn (array $conversation) => $this->channelFor($user, $conversation), $this->listConversations($client))));
            $this->connection->forceFill(['direct_history_total' => count($channels)])->save();
            $totals['conversations'] = count($channels);
            $startedAt               = $this->connection->direct_history_started_at;

            foreach ($channels as $channel) {
                if (!SyncSlackPrivateChannels::stillOptedIn($user->id)) {
                    return $totals;
                }
                if ($channel->history_synced_at?->gte($startedAt)) {
                    $totals['done']++;

                    continue;
                }
                if (now()->gte($stopAt)) {
                    $totals['timed_out'] = true;

                    return $totals;
                }
                $members = Arr::get($this->slackCall($client, 'conversations.members', ['channel' => $channel->slack_id, 'limit' => 100]), 'members', []);
                StoreSlackDirectMessage::make()->addMembers($channel, [$slackPerson->slack_id, ...array_filter((array) $members, 'is_string')]);
                // shortcut: resume state is per conversation, so if another person's walk stopped halfway, messages sent after it stopped and before this walk are not read; a forward read from the newest stored ts would close it.
                $this->syncHistory($client, $channel);
                if (!$channel->refresh()->history_synced_at?->gte($startedAt)) {
                    return $totals;
                }
                $totals['done']++;
            }
        } catch (Throwable $exception) {
            SyncSlackPrivateChannels::flagReconnect($this->connection, $exception);
            $totals['failed'][] = $exception::class;

            return $totals;
        }

        if (!$gapsFilled) {
            $totals['failed'][] = 'gaps not all filled';

            return $totals;
        }
        $this->connection->forceFill(['direct_history_finished_at' => now()])->save();
        $totals['finished'] = true;

        return $totals;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listConversations(PendingRequest $client): array
    {
        $conversations = [];
        $cursor        = null;
        do {
            $page = $this->slackCall($client, 'users.conversations', array_filter(['types' => 'im,mpim', 'limit' => 200, 'cursor' => $cursor]));
            foreach ((array) Arr::get($page, 'channels', []) as $conversation) {
                if (is_array($conversation) && is_string(Arr::get($conversation, 'id')) && (Arr::get($conversation, 'is_im') || Arr::get($conversation, 'is_mpim'))) {
                    $conversations[] = $conversation;
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        return $conversations;
    }

    /**
     * The live path's rule: a conversation is kept only as a direct row that is not ignored or deleted.
     *
     * @param  array<string, mixed>  $conversation
     */
    private function channelFor(User $user, array $conversation): ?SlackChannel
    {
        $channel = SlackChannel::firstOrCreate(
            ['slack_id' => $conversation['id']],
            ['group_id' => $user->group_id, 'direct_type' => Arr::get($conversation, 'is_im') ? 'im' : 'mpim', 'is_private' => true, 'is_public' => false, 'is_member' => false]
        );

        return SlackChannel::keptDirect()->whereKey($channel->id)->first();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function storeHistoryMessage(SlackChannel $channel, array $message): ?SlackMessage
    {
        $stored = StoreSlackDirectMessage::isFromPerson($message) ? StoreSlackDirectMessage::make()->store($channel, $message, $this->bringer) : null;
        if ($stored?->data !== null) {
            CopySlackMessageFile::dispatchFor($stored);
        }

        return $stored;
    }

    protected function isStillKept(SlackChannel $channel): bool
    {
        return $this->connection
            && SlackChannel::keptDirect()->whereKey($channel->id)->exists()
            && SyncSlackPrivateChannels::stillOptedIn($this->connection->user_id);
    }

    /**
     * People who opted in whose walk has not finished, oldest connection first.
     */
    public static function waiting(): \Illuminate\Database\Eloquent\Builder
    {
        return SlackConnection::main()->live()->whereNotNull('private_consented_at')->whereNull('direct_history_finished_at')->orderBy('id');
    }

    /**
     * Queues the first person still waiting whose token may be used, so walks run one after another. After a run that stopped
     * without finishing it looks only past that person's connection, so one stuck person never holds up the rest, nor loops.
     */
    public static function startNext(?int $afterConnectionId = null): ?int
    {
        $next = self::waiting()->when($afterConnectionId, fn ($query) => $query->where('id', '>', $afterConnectionId))->get()->first(fn (SlackConnection $connection) => SyncSlackPrivateChannels::stillOptedIn($connection->user_id));
        if ($next) {
            self::dispatch($next->user_id);
        }

        return $next?->user_id;
    }

    /**
     * @return array{done: int, total: int, finished: bool}|null
     */
    public static function progress(?SlackConnection $connection): ?array
    {
        if (!$connection?->direct_history_started_at || $connection->direct_history_total === null) {
            return null;
        }
        $done = SlackChannel::keptDirect()
            ->where('history_synced_at', '>=', $connection->direct_history_started_at)
            ->whereHas('members', fn ($query) => $query->where('slack_user_id', $connection->slack_user_id))
            ->count();

        return ['done' => min($done, $connection->direct_history_total), 'total' => $connection->direct_history_total, 'finished' => $connection->direct_history_finished_at !== null];
    }

    public function asJob(int $userId): void
    {
        if ($user = User::find($userId)) {
            $this->handle($user);
        }
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $totals = SlackSyncRun::record(self::COMMAND, fn () => ['waiting' => self::waiting()->count(), 'started' => (int) (self::startNext() !== null)]);
        $command->info("People waiting: {$totals['waiting']}, started: {$totals['started']}");

        return 0;
    }
}
