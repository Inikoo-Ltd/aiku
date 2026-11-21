<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack;

use App\Actions\Chat\Staff\Json\SearchSlackMessages;
use App\Actions\Helpers\Ticket\SendTicketSlackDirectMessage;
use App\Actions\SysAdmin\User\SendUserPushNotification;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use App\Notifications\SlackMentionNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Ramsey\Uuid\Uuid;

/**
 * Tells the aiku users a live Slack message mentions (<@U…>), once per person and message, only if they chose a channel
 * for it on their profile and may see the channel. Called from StoreSlackMessage::handle (webhook events) right after the
 * store, so a new message counts only when this store created it, and an edit only for people the replaced wording did
 * not mention. History syncs call store() and never reach here. <!here>, <!channel> and user groups are not followed.
 */
class NotifySlackMentions
{
    use AsAction;

    public const array SUBTYPES = [null, 'thread_broadcast', 'file_share', 'me_message'];

    public function handle(SlackMessage $message): void
    {
        if (!in_array($message->subtype, self::SUBTYPES, true)) {
            return;
        }

        $mentioned = $this->newlyMentioned($message);
        if ($mentioned === []) {
            return;
        }

        $channel = $message->channel;
        $users   = $this->linkedUsers(array_diff($mentioned, [(string) $message->slack_user_id]))
            ->reject(fn (User $user) => $user->id === $message->user_id)
            ->filter(fn (User $user) => UserNotificationEnum::SLACK_MENTION->channelsFor($user) !== [] && $channel->isVisibleTo($user));
        if ($users->isEmpty()) {
            return;
        }

        $title = __(':name mentioned you in #:channel', ['name' => $this->authorName($message), 'channel' => $channel->name]);
        $body  = $this->excerpt($message);
        $url   = SearchSlackMessages::messageUrl($message);

        foreach ($users as $user) {
            rescue(fn () => $this->notify($user, $message, $title, $body, $url));
        }
    }

    /**
     * NotifyUserOnChosenChannels, except that the Slack direct message escapes the text: the excerpt is what someone
     * typed in Slack, decoded, and must not become a link or a mention in the bot's message.
     */
    private function notify(User $user, SlackMessage $message, string $title, string $body, string $url): void
    {
        $id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'aiku:slack-mention/'.$message->id.'/'.$user->id)->toString();
        if (DatabaseNotification::whereKey($id)->exists()) {
            return;
        }

        $channels               = UserNotificationEnum::SLACK_MENTION->deliverTo($user);
        $notification           = new SlackMentionNotification($message, $title, $body, $url);
        $notification->id       = $id;
        $notification->byEmail  = in_array('email', $channels, true) && (bool) $user->email;
        $user->notify($notification);

        if (in_array('slack', $channels, true) && $user->slack_user_id) {
            SendTicketSlackDirectMessage::dispatch($user, '*'.self::escapeForSlack($title)."*\n".self::escapeForSlack($body).' <'.$url.'|'.self::escapeForSlack(__('Open in aiku')).'>');
        }

        if (in_array('browser', $channels, true) && $user->pushSubscriptions()->exists()) {
            SendUserPushNotification::dispatch($user, ['title' => $title, 'body' => $body, 'url' => $url, 'tag' => UserNotificationEnum::SLACK_MENTION->value]);
        }
    }

    public static function escapeForSlack(string $text): string
    {
        return strtr($text, ['&' => '&amp;', '<' => '&lt;', '>' => '&gt;']);
    }

    /**
     * @return list<string>
     */
    private function newlyMentioned(SlackMessage $message): array
    {
        if ($message->wasRecentlyCreated) {
            return self::mentionsIn($message->text);
        }
        if (!$message->wasChanged('text')) {
            return [];
        }

        return array_values(array_diff(self::mentionsIn($message->text), self::mentionsIn($message->versions()->reorder('id', 'desc')->value('text'))));
    }

    /**
     * @return list<string>
     */
    public static function mentionsIn(?string $text): array
    {
        preg_match_all('/<@([UW][A-Z0-9]+)(?:\|[^>]*)?>/', (string) $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<int, string>  $slackIds
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function linkedUsers(array $slackIds)
    {
        if ($slackIds === []) {
            return collect();
        }
        return User::where('status', true)
            ->whereExists(fn ($query) => $query->from('slack_users')
                ->whereColumn('slack_users.user_id', 'users.id')
                ->whereIn('slack_users.slack_id', $slackIds)
                ->where('slack_users.is_bot', false)
                ->where('slack_users.is_deleted', false)
                ->whereRaw('lower(slack_users.email) = lower(users.email)')
                ->whereRaw("coalesce((slack_users.data->>'is_restricted')::boolean, false) = false"))
            ->orderBy('id')
            ->get();
    }

    private function authorName(SlackMessage $message): string
    {
        $slackUser = $message->slack_user_id ? SlackUser::where('slack_id', $message->slack_user_id)->first(['display_name', 'real_name', 'name']) : null;

        return $message->user?->chatName()
            ?? ($slackUser?->display_name ?: $slackUser?->real_name ?: $slackUser?->name)
            ?? __('Someone');
    }

    private function excerpt(SlackMessage $message): string
    {
        $nodes = FormatSlackMessages::make()->handle(collect([$message]));

        return Str::limit(Str::squish(RefreshSlackMessageSearch::readableText($nodes[$message->id] ?? [], $message->data ?? [])), 140);
    }
}
