<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Slack\Write\SlackWriteAccess;
use App\Actions\Chat\Staff\Slack\Write\SlackWriteClient;
use App\Actions\Helpers\Ticket\Concerns\WithSlack;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackSyncRun;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

/**
 * A private channel comes whole when a member adds the aiku bot to it, or when a member who opted in to private conversations
 * (INI-429) connects their own Slack (INI-431/564, decided 26 Nov 2026 under INI-427). With the bot token Slack lists only the
 * private channels the bot is in; with a person's token, only the ones that person is in. Each channel is one row, whoever
 * brought it, and is read once per run with the token of someone in it: the bot when it is in, otherwise an opted-in member.
 * Stored rows are read by the channel's members only; kept() never includes them.
 */
class SyncSlackPrivateChannels
{
    use AsAction;
    use WithSlack;
    use WithSlackChannelHistory;

    public const string COMMAND = 'slack:sync-private-channels';

    public string $commandSignature = 'slack:sync-private-channels
        {--history : Bring the past messages of the private channels the aiku app or an opted-in member is in}
        {--channel= : Only this Slack channel id}';

    public string $commandDescription = 'Keep the private Slack channels the aiku app was added to or an opted-in member brought, with their members and messages';

    private ?SlackConnection $readingAs = null;

    /**
     * @return array{channels: int, members: int, messages: int, added: int, failed: list<string>}
     */
    public function handle(Group $group, bool $history = false, ?string $onlySlackId = null, ?int $onlyUserId = null): array
    {
        $totals      = ['channels' => 0, 'members' => 0, 'messages' => 0, 'failed' => []];
        $this->added = 0;
        $read        = [];

        if ($onlyUserId === null) {
            $client = $this->slackClient() ?? throw new RuntimeException('Slack bot token is not set');
            $listed = [];
            $this->readingAs = null;
            foreach ($this->listPrivateChannels($client, $onlySlackId) as $slackChannel) {
                $slackId  = (string) Arr::get($slackChannel, 'id');
                $listed[] = $slackId;
                if (SlackChannel::where('slack_id', $slackId)->where('is_ignored', true)->exists()) {
                    continue;
                }
                $channel = SlackChannel::updateOrCreate(['slack_id' => $slackId], [
                    'group_id'    => $group->id,
                    'is_member'   => true,
                    ...$this->channelAttributes($slackChannel),
                ]);
                $read[$slackId] = true;
                $this->bring($client, $channel, $history, $totals);
            }

            if (!$onlySlackId) {
                SlackChannel::where('is_private', true)->where('is_member', true)->whereNotIn('slack_id', $listed)->update(['is_member' => false]);
            }
        }

        foreach ($this->optedInConnections($onlyUserId) as $connection) {
            $client = $this->clientFor($connection);
            if (!$client) {
                continue;
            }
            try {
                $listedByPerson = $this->listPrivateChannels($client, $onlySlackId);
            } catch (Throwable $exception) {
                $this->flagReconnect($connection, $exception);
                $totals['failed'][] = "connection of user $connection->user_id: {$exception->getMessage()}";

                continue;
            }
            $this->readingAs = $connection;
            foreach ($listedByPerson as $slackChannel) {
                if (!self::stillOptedIn($connection->user_id)) {
                    break;
                }
                $slackId = (string) Arr::get($slackChannel, 'id');
                if (isset($read[$slackId]) || SlackChannel::where('slack_id', $slackId)->where(fn ($query) => $query->where('is_ignored', true)->orWhere('is_member', true))->exists()) {
                    continue;
                }
                $read[$slackId] = true;
                $channel        = SlackChannel::firstOrCreate(['slack_id' => $slackId], [
                    'group_id'           => $group->id,
                    'is_member'          => false,
                    'brought_by_user_id' => $connection->user_id,
                    ...$this->channelAttributes($slackChannel),
                ]);
                $announce = $channel->wasRecentlyCreated;
                if (!$announce) {
                    $announce = !$channel->brought_by_user_id || !self::stillOptedIn($channel->brought_by_user_id);
                    $channel->update([...$this->channelAttributes($slackChannel), ...($announce ? ['brought_by_user_id' => $connection->user_id] : [])]);
                }
                if ($this->bring($client, $channel, $history, $totals) && $announce) {
                    $this->announceInSlack($client, $connection, $channel, $totals);
                }
            }
            $this->readingAs = null;
        }

        return $totals + ['added' => $this->added];
    }

    /**
     * Whether anything new may still be stored for this private channel: the bot is in it, or a member's own connection
     * still holds the private opt-in. A Slack event alone never counts, as Slack keeps sending it after a person withdrew.
     */
    public static function isBroughtLive(SlackChannel $channel): bool
    {
        return $channel->is_member || self::optedInConnectionsQuery()
            ->whereIn('slack_user_id', $channel->members()->select('slack_user_id'))
            ->get()
            ->contains(fn (SlackConnection $connection) => self::isUsable($connection));
    }

    /**
     * Re-read before each use of a person's token, so a person who withdrew while the run was going is never used again.
     */
    private static function stillOptedIn(int $userId): bool
    {
        $connection = self::optedInConnectionsQuery()->where('user_id', $userId)->first();

        return $connection !== null && self::isUsable($connection);
    }

    private static function isUsable(SlackConnection $connection): bool
    {
        return $connection->hasPrivateConsent() && !$connection->user->worksOnlyForAgents();
    }

    private static function optedInConnectionsQuery(): Builder
    {
        return SlackConnection::live()->whereNotNull('private_consented_at')->whereHas('user', fn (Builder $query) => $query->where('status', true));
    }

    /**
     * @return Collection<int, SlackConnection>
     */
    private function optedInConnections(?int $onlyUserId): Collection
    {
        return self::optedInConnectionsQuery()
            ->when($onlyUserId, fn (Builder $query) => $query->where('user_id', $onlyUserId))
            ->orderBy('id')
            ->get()
            ->filter(fn (SlackConnection $connection) => self::isUsable($connection))
            ->values();
    }

    /**
     * A person's token reads only where a person's token may write: a local machine can hold a copy of production tokens.
     */
    private function clientFor(SlackConnection $connection): ?PendingRequest
    {
        if (!app()->environment(SlackWriteAccess::WRITE_ENVIRONMENTS)) {
            return null;
        }
        try {
            return Http::withToken((string) $connection->token)->baseUrl('https://slack.com/api');
        } catch (DecryptException) {
            $connection->forceFill(['needs_reconnect' => true])->save();

            return null;
        }
    }

    private function flagReconnect(SlackConnection $connection, Throwable $exception): void
    {
        foreach (SlackWriteClient::RECONNECT_ERRORS as $error) {
            if (str_ends_with($exception->getMessage(), ": $error")) {
                $connection->forceFill(['needs_reconnect' => true])->save();
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listPrivateChannels(PendingRequest $client, ?string $onlySlackId): array
    {
        $channels = [];
        $cursor   = null;
        do {
            $page = $this->slackCall($client, 'conversations.list', array_filter(['types' => 'private_channel', 'limit' => 200, 'cursor' => $cursor]));
            foreach (Arr::get($page, 'channels', []) as $slackChannel) {
                if (Arr::get($slackChannel, 'is_private') && (!$onlySlackId || Arr::get($slackChannel, 'id') === $onlySlackId)) {
                    $channels[] = $slackChannel;
                }
            }
            $cursor = Arr::get($page, 'response_metadata.next_cursor');
        } while ($cursor);

        return $channels;
    }

    /**
     * @param  array<string, mixed>  $slackChannel
     * @return array<string, mixed>
     */
    private function channelAttributes(array $slackChannel): array
    {
        return [
            'name'        => Arr::get($slackChannel, 'name'),
            'is_archived' => (bool) Arr::get($slackChannel, 'is_archived'),
            'is_public'   => false,
            'is_private'  => true,
            'data'        => $slackChannel,
        ];
    }

    /**
     * Transparency (INI-431 comment 36778, Raul 27 Nov 2026): the people still on Slack see, as the person who brought it, that
     * the channel is now in Verba. Posted after the channel was read, when that person's connection stores it first or takes it over
     * from a bot that left or a bringer who withdrew; a failed post never stops the import.
     *
     * @param  array{channels: int, members: int, messages: int, failed: list<string>}  $totals
     */
    private function announceInSlack(PendingRequest $client, SlackConnection $connection, SlackChannel $channel, array &$totals): void
    {
        $name = NotifySlackMentions::escapeForSlack((string) $connection->user?->chatName());
        try {
            $this->slackCall($client, 'chat.postMessage', ['channel' => $channel->slack_id, 'text' => "This channel is now in Verba, brought by $name"], 'post');
        } catch (Throwable $exception) {
            $totals['failed'][] = "$channel->name ($channel->slack_id) announcement: {$exception->getMessage()}";
        }
    }

    /**
     * @param  array{channels: int, members: int, messages: int, failed: list<string>}  $totals
     */
    private function bring(PendingRequest $client, SlackChannel $channel, bool $history, array &$totals): bool
    {
        $totals['channels']++;
        try {
            $totals['members'] += $this->syncMembers($client, $channel);
            if ($history) {
                $totals['messages'] += $this->syncHistory($client, $channel);
            }

            return true;
        } catch (Throwable $exception) {
            $totals['failed'][] = "$channel->name ($channel->slack_id): {$exception->getMessage()}";

            return false;
        }
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
        if (!$this->readingAs) {
            return SlackChannel::keptPrivate()->whereKey($channel->id)->where('is_member', true)->exists();
        }

        return SlackChannel::keptPrivate()->whereKey($channel->id)->exists()
            && $channel->members()->where('slack_user_id', $this->readingAs->slack_user_id)->exists()
            && (bool) SlackConnection::whereKey($this->readingAs->id)->first()?->hasPrivateConsent();
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
