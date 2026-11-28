<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Slack\Write\SendSlackFiles;
use App\Events\SlackDirectMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackChannelMember;
use App\Models\Chat\SlackConnection;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Notifications\SlackDirectInviteNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * The DM path (INI-431): a Slack direct message or group DM delivered through the user token of a person who opted in.
 * Each author brings only their own words: a message keeps its text, data and files only while its author has opted in;
 * any other message is a gap (ts, conversation, author, thread and sent time) even though the delivering token returned its text.
 * Called inline from the webhook and from FillSlackDirectGaps, never queued with the message, so no text sits in a queue payload.
 */
class StoreSlackDirectMessage
{
    use AsAction;

    public const array TYPES = ['im', 'mpim'];

    public const string INVITE_NAMESPACE = 'aiku:slack-direct-invite/';

    /**
     * @param  array<string, mixed>  $event
     * @param  array<string, mixed>  $authorization  the envelope's authorizations.0: whose installation delivered the event
     */
    public function handle(array $event, array $authorization): ?SlackMessage
    {
        $type           = Arr::get($event, 'channel_type');
        $slackChannelId = Arr::get($event, 'channel');
        $bringer        = $this->bringer($authorization);
        if (!in_array($type, self::TYPES, true) || !is_string($slackChannelId) || !$bringer) {
            return null;
        }

        $subtype = Arr::get($event, 'subtype');
        if ($subtype === 'message_deleted') {
            $channel = SlackChannel::keptDirect()->where('slack_id', $slackChannelId)->first();
            $message = $channel?->messages()->where('ts', (string) Arr::get($event, 'deleted_ts'))->first();
            $message?->delete();
            if ($message) {
                SlackDirectMessageChanged::announce($channel, $message, 'deleted');
            }

            return $message;
        }

        $message = $subtype === 'message_changed' ? (array) Arr::get($event, 'message', []) : $event;
        $author  = Arr::get($message, 'user');
        if (!self::isFromPerson($message)) {
            return null;
        }

        $channel = SlackChannel::firstOrCreate(
            ['slack_id' => $slackChannelId],
            ['group_id' => $bringer->user->group_id, 'direct_type' => $type, 'is_private' => true, 'is_public' => false, 'is_member' => false]
        );
        if (!SlackChannel::keptDirect()->whereKey($channel->id)->exists()) {
            return null;
        }
        $this->addMembers($channel, [$bringer->slack_user_id, $author]);

        $stored = $this->store($channel, $message, $bringer->user);
        if ($stored && !$stored->sent_from_aiku && $stored->data !== null && self::carriesFileSentFromAiku([...$message, 'channel' => $slackChannelId])) {
            $stored->forceFill(['sent_from_aiku' => true])->saveQuietly();
        }
        if ($stored?->data !== null) {
            CopySlackMessageFile::dispatchFor($stored);
        }
        if ($stored) {
            SlackDirectMessageChanged::announce($channel, $stored, $stored->wasRecentlyCreated ? 'new' : 'changed');
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function store(SlackChannel $channel, array $message, ?User $bringer = null): ?SlackMessage
    {
        $ts     = Arr::get($message, 'ts');
        $author = Arr::get($message, 'user');
        if (!is_string($ts) || !is_string($author)) {
            return null;
        }

        if (self::hasOptedIn($author)) {
            $message = self::withoutOthersWords($message);
            $gap = SlackMessage::where('slack_channel_id', $channel->id)->where('ts', $ts)->whereNull('data')->first();
            if ($gap) {
                $gap->update([
                    'thread_ts' => Arr::get($message, 'thread_ts'),
                    'subtype'   => Arr::get($message, 'subtype'),
                    'text'      => Arr::get($message, 'text'),
                    'edited_at' => ($edited = Arr::get($message, 'edited.ts')) ? Carbon::createFromTimestamp((float) $edited) : null,
                    'data'      => $message,
                ]);

                return $gap;
            }

            return StoreSlackMessage::make()->store($channel, $message);
        }

        $gap = SlackMessage::withTrashed()->firstOrCreate(['slack_channel_id' => $channel->id, 'ts' => $ts], [
            'thread_ts'     => Arr::get($message, 'thread_ts'),
            'slack_user_id' => $author,
            'user_id'       => SlackUser::where('slack_id', $author)->value('user_id'),
            'sent_at'       => Carbon::createFromTimestamp((float) $ts),
        ]);
        if ($gap->wasRecentlyCreated && $bringer) {
            rescue(fn () => $this->invite($gap, $bringer), fn (Throwable $exception) => Log::warning('Slack DM invite not sent', ['user_id' => $gap->user_id, 'error' => $exception::class]), false);
        }

        return $gap;
    }

    /**
     * A message keeps only its author's words: a broadcast reply carries its root, a shared or quoted message its text, and those
     * may belong to someone who has not opted in.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    public static function withoutOthersWords(array $message): array
    {
        unset($message['root'], $message['previous_message']);
        if (isset($message['attachments']) && is_array($message['attachments'])) {
            $message['attachments'] = array_values(array_filter($message['attachments'], fn ($attachment) => is_array($attachment) && empty($attachment['is_share']) && empty($attachment['is_msg_unfurl']) && !isset($attachment['msg_subtype']) && !isset($attachment['author_id'])));
            if ($message['attachments'] === []) {
                unset($message['attachments']);
            }
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    /**
     * A person's own message. One posted with the person's own token through an app (Verba included) carries the app's bot_id
     * beside the person: it is theirs when the author is a known Slack person, not a bot, and it is not a bot_message.
     *
     * @param  array<string, mixed>  $message
     */
    public static function isFromPerson(array $message): bool
    {
        $author = Arr::get($message, 'user');
        if (!is_string($author)) {
            return false;
        }
        $isBot = SlackUser::where('slack_id', $author)->value('is_bot');
        if ($isBot) {
            return false;
        }

        return !Arr::get($message, 'bot_id') || ($isBot === false && Arr::get($message, 'subtype') !== 'bot_message');
    }

    /**
     * Files sent from Verba leave a short-lived marker per Slack file id (SendSlackFiles): their message only arrives with Slack's event.
     *
     * @param  array<string, mixed>  $message
     */
    private static function carriesFileSentFromAiku(array $message): bool
    {
        $sender = SendSlackFiles::sentMarkerValue((string) Arr::get($message, 'user'), (string) Arr::get($message, 'channel'));

        return collect((array) Arr::get($message, 'files', []))
            ->contains(fn ($file) => is_array($file) && is_string($id = Arr::get($file, 'id')) && Cache::get(SendSlackFiles::sentMarkerKey($id)) === $sender);
    }

    public static function hasOptedIn(string $slackUserId): bool
    {
        return SlackConnection::main()->where('slack_user_id', $slackUserId)->live()->whereNotNull('private_consented_at')->get()
            ->contains(fn (SlackConnection $connection) => $connection->hasPrivateConsent());
    }

    /**
     * @param  array<string, mixed>  $authorization
     */
    private function bringer(array $authorization): ?SlackConnection
    {
        $slackUserId = Arr::get($authorization, 'user_id');
        if (Arr::get($authorization, 'is_bot') !== false || !is_string($slackUserId)
            || (config('services.slack.team_id') && Arr::get($authorization, 'team_id') !== config('services.slack.team_id'))) {
            return null;
        }

        return SlackConnection::main()->where('slack_user_id', $slackUserId)->live()->whereNotNull('private_consented_at')->with('user')->get()
            ->first(fn (SlackConnection $connection) => $connection->hasPrivateConsent() && $connection->user && SlackChannel::privateReader($connection->user)?->slack_id === $slackUserId);
    }

    /**
     * @param  array<int, string>  $slackUserIds
     */
    public function addMembers(SlackChannel $channel, array $slackUserIds): void
    {
        $rows = collect($slackUserIds)->filter()->unique()->map(fn (string $slackUserId) => [
            'slack_channel_id' => $channel->id,
            'slack_user_id'    => $slackUserId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ])->values()->all();
        if ($rows !== []) {
            SlackChannelMember::insertOrIgnore($rows);
        }
    }

    /**
     * One invite per person, ever (INI-427 section 3), the first time one of their messages becomes a gap.
     */
    private function invite(SlackMessage $gap, User $bringer): void
    {
        $user = $gap->user_id ? User::find($gap->user_id) : null;
        if (!$user || !$user->status || $user->id === $bringer->id || !SlackChannel::privateReader($user)) {
            return;
        }
        $id = Uuid::uuid5(Uuid::NAMESPACE_URL, self::INVITE_NAMESPACE.$user->id)->toString();
        if (DatabaseNotification::whereKey($id)->exists()) {
            return;
        }

        $name               = $bringer->chatName();
        $notification       = new SlackDirectInviteNotification(
            __('Your chat with :name is waiting for you in Verba 👋', ['name' => $name]),
            __(':name has brought their side over. Yours shows as a little gap until you bring it too. No rush: it\'s entirely your choice.', ['name' => $name]),
            route('grp.profile.edit', ['section' => 'slack'])
        );
        $notification->id   = $id;
        $user->notify($notification);
    }
}
