<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Nov 2026 13:10:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Home\QuickEditStaffMessageStory;
use App\Actions\Chat\Staff\Slack\Write\SendSlackFiles;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreSlackMessage
{
    use AsAction;

    /**
     * A message event of the Slack Events API. Only public channels are kept: private channels and direct messages are never stored.
     * An event that does not say what kind of channel it is from is kept only for a channel already known as public.
     *
     * @param  array<string, mixed>  $event
     */
    public function handle(array $event): ?SlackMessage
    {
        $slackChannelId = Arr::get($event, 'channel');
        $channelType    = Arr::get($event, 'channel_type');
        if (!$slackChannelId || ($channelType !== null && $channelType !== 'channel')) {
            return null;
        }

        if ($channelType === 'channel') {
            SlackChannel::firstOrCreate(['slack_id' => $slackChannelId], ['group_id' => Group::firstOrFail()->id, 'is_member' => true]);
        }
        $channel = SlackChannel::kept()->where('slack_id', $slackChannelId)->first();
        if (!$channel) {
            return null;
        }

        $subtype = Arr::get($event, 'subtype');
        $message = match ($subtype) {
            'message_deleted' => $this->delete($channel, (string) Arr::get($event, 'deleted_ts')),
            'message_changed' => $this->store($channel, (array) Arr::get($event, 'message', [])),
            default           => $this->store($channel, $event),
        };

        if (!$message) {
            return null;
        }

        $isNew = $message->wasRecentlyCreated && $subtype !== 'message_changed';
        if (!$message->sent_from_aiku && !$message->trashed() && $this->carriesFileSentFromAiku($message)) {
            $message->forceFill(['sent_from_aiku' => true])->saveQuietly();
        }
        rescue(fn () => SlackChannelMessageChanged::dispatch($channel, $message, $message->trashed() ? 'deleted' : ($isNew ? 'new' : 'changed')));

        if (!$message->trashed()) {
            foreach ((array) Arr::get($message->data, 'files', []) as $file) {
                CopySlackMessageFile::dispatch($message, (array) $file);
            }
            if ($isNew || !$message->wasRecentlyCreated) {
                rescue(fn () => NotifySlackMentions::run($message));
            }
            if ($isNew && QuickEditStaffMessageStory::isCandidate($message)) {
                QuickEditStaffMessageStory::dispatch($message);
            }
        }

        return $message;
    }

    /**
     * Files sent from aiku leave a short-lived marker per Slack file id (SendSlackFiles), since the message itself only arrives with Slack's event.
     */
    private function carriesFileSentFromAiku(SlackMessage $message): bool
    {
        foreach ((array) Arr::get($message->data, 'files', []) as $file) {
            if (is_array($file) && ($id = Arr::get($file, 'id')) && Cache::has(SendSlackFiles::sentMarkerKey((string) $id))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Stores a message as Slack gives it, in an event or in the channel history; the same message twice is one row.
     *
     * @param  array<string, mixed>  $message
     */
    public function store(SlackChannel $channel, array $message): ?SlackMessage
    {
        $ts = Arr::get($message, 'ts');
        if (!$ts) {
            return null;
        }

        $slackUserId = Arr::get($message, 'user');
        $editedTs    = Arr::get($message, 'edited.ts');

        return DB::transaction(function () use ($channel, $ts, $message, $slackUserId, $editedTs) {
            $stored = SlackMessage::withTrashed()->where('slack_channel_id', $channel->id)->where('ts', $ts)->lockForUpdate()->first();
            if ($stored?->edited_at && (!$editedTs || $stored->edited_at->gt(Carbon::createFromTimestamp((float) $editedTs)))) {
                return $stored;
            }
            $this->keepEarlierVersion($stored, $message, $editedTs);
            if ($stored?->slack_user_id && !$slackUserId && Arr::get($message, 'bot_id')) {
                $slackUserId = $stored->slack_user_id;
                $message     = [...$message, 'subtype' => $stored->subtype];
            }

            return tap(SlackMessage::withTrashed()->updateOrCreate(
                ['slack_channel_id' => $channel->id, 'ts' => $ts],
                [
                    'thread_ts'     => Arr::get($message, 'thread_ts'),
                    'slack_user_id' => $slackUserId,
                    'user_id'       => $slackUserId ? (SlackUser::where('slack_id', $slackUserId)->value('user_id') ?? User::where('slack_user_id', $slackUserId)->value('id')) : null,
                    'subtype'       => Arr::get($message, 'subtype'),
                    'text'          => Arr::get($message, 'text'),
                    'sent_at'       => Carbon::createFromTimestamp((float) $ts),
                    'edited_at'     => $editedTs ? Carbon::createFromTimestamp((float) $editedTs) : null,
                    'data'          => $message,
                ]
            ), fn (SlackMessage $stored) => StoreSlackMessageReaction::make()->fromStoredMessage($stored));
        });
    }

    /**
     * Slack sends message_changed for link previews, reply counts and files finishing too; only a new wording keeps the old one.
     * A message that arrives older than the edit already stored is left alone before this is reached.
     *
     * @param  array<string, mixed>  $message
     */
    private function keepEarlierVersion(?SlackMessage $stored, array $message, ?string $editedTs): void
    {
        if ($stored && (string) $stored->text !== (string) Arr::get($message, 'text')) {
            $stored->versions()->create([
                'text'        => $stored->text,
                'data'        => $stored->data,
                'replaced_at' => $editedTs ? Carbon::createFromTimestamp((float) $editedTs) : now(),
            ]);
        }
    }

    private function delete(SlackChannel $channel, string $ts): ?SlackMessage
    {
        $message = $channel->messages()->where('ts', $ts)->first();
        $message?->delete();

        return $message;
    }
}
