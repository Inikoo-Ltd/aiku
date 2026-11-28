<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\Slack\SlackWorkspace;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * What reacting to, editing and deleting a Slack message from aiku share: who may, which message, and Slack's
 * answers as short sentences under the control that was clicked.
 */
trait WithSlackMessageWrite
{
    public function authorize(ActionRequest $request): bool
    {
        $slackChannel = $request->route('slackChannel');

        return $slackChannel instanceof SlackChannel && $this->mayWriteHere($request->user(), $slackChannel);
    }

    /**
     * Only sending, editing and deleting open Slack DMs and group DMs (INI-593); every other write stays on public channels.
     */
    protected function writesDirect(): bool
    {
        return false;
    }

    /**
     * Members only, as directVisibleTo, no role bypass. The private opt-in and a live token are asked by clientFor, so
     * someone without them gets the reason under the box rather than a refusal.
     */
    public static function mayWriteDirect(User $user, SlackChannel $slackChannel): bool
    {
        return $slackChannel->direct_type !== null && $slackChannel->slack_team_id === null && !$slackChannel->is_archived && SlackWriteAccess::allows($user) && $slackChannel->isDirectVisibleTo($user);
    }

    protected function mayWriteHere(User $user, SlackChannel $slackChannel): bool
    {
        return $slackChannel->direct_type ? $this->writesDirect() && self::mayWriteDirect($user, $slackChannel) : self::mayWrite($user, $slackChannel);
    }

    public static function mayWrite(User $user, SlackChannel $slackChannel): bool
    {
        return SlackWriteAccess::allows($user) && $slackChannel->isVisibleTo($user) && !$slackChannel->is_archived;
    }

    /**
     * handle() asks it too, so a caller of run() cannot skip what authorize() checks for a request.
     */
    protected function ensureMayWrite(User $user, SlackChannel $slackChannel): void
    {
        if (!$this->mayWriteHere($user, $slackChannel)) {
            $this->refuse(__('Messages cannot be changed in this channel'));
        }
    }

    protected function liveMessage(SlackChannel $slackChannel, string $ts): SlackMessage
    {
        return SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $ts)->firstOrFail();
    }

    /**
     * The person's own connection to the channel's workspace (INI-540); without one, posting, editing and deleting go
     * through that workspace's bot in their place.
     */
    protected function clientFor(User $user, bool $standInAllowed = false, ?SlackChannel $channel = null): SlackWriteClient
    {
        if ($channel?->direct_type) {
            $client = SlackWriteAccess::directReasonUnavailable($user) === null ? SlackWriteClient::for($user) : null;
            if (!$client) {
                $this->refuse(SlackWriteAccess::directReasonUnavailable($user) ?? __('Reconnect your Slack account to reply here'));
            }

            return $client;
        }
        $workspace = $channel ? SlackWorkspace::forChannel($channel) : SlackWorkspace::main();
        $client    = $workspace ? (SlackWriteClient::for($user, $workspace) ?? ($standInAllowed ? SlackWriteClient::standInFor($user, $workspace) : null)) : null;
        if (!$client) {
            $this->refuse(($workspace ? SlackWriteAccess::reasonUnavailable($user, $workspace) : null) ?? __('Connect your Slack account first'));
        }

        return $client;
    }

    /**
     * Only a person's own message: its Slack author is the person of the connection, and it is neither a bot_message
     * nor a system notice.
     * A message sent from aiku carries the app's bot_id beside the person's user, so bot_id alone never refuses.
     */
    protected function ensureOwnMessage(SlackMessage $message, SlackWriteClient $client): void
    {
        $isOwn = $message->slack_user_id !== null
            && $message->slack_user_id === $client->slackUserId()
            && (!$client->isStandIn() || $message->sent_from_aiku)
            && !GetSlackChannelMessages::isBotMessage($message)
            && !in_array($message->subtype, GetSlackChannelMessages::SYSTEM_SUBTYPES, true);

        if (!$isOwn) {
            $this->refuse(__('You can only change your own messages'));
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $alsoSuccess  Slack answers that mean our copy was behind, not that anything failed
     * @return array<string, mixed>
     */
    protected function callSlack(SlackWriteClient $client, string $method, array $parameters, array $alsoSuccess = []): array
    {
        try {
            return $client->call($method, $parameters);
        } catch (SlackWriteException $exception) {
            $code = $exception->slackError;
            if (in_array($code, $alsoSuccess, true)) {
                return ['ok' => true];
            }
            $this->refuse(self::sentenceFor($code));
        }
    }

    public static function sentenceFor(?string $code): string
    {
        return match ($code) {
            'cant_update_message'  => __('Slack does not let you edit this message'),
            'edit_window_closed'   => __('The time allowed to change this message in Slack has passed'),
            'message_not_found'    => __('This message is no longer in Slack'),
            'cant_delete_message'  => __('Slack does not let you delete this message'),
            'too_many_emoji'       => __('This message already has as many different reactions as Slack allows'),
            'too_many_reactions'   => __('You have added as many reactions as Slack allows'),
            'invalid_name'         => __('Slack does not know this emoji'),
            'is_archived', 'is_inactive', 'channel_not_found' => __('This channel can no longer be written to in Slack'),
            'unreachable'          => __('Slack could not be reached. Try again in a minute'),
            'too_many_pins'        => __('This channel already has as many pinned messages as Slack allows'),
            'invalid_link', 'bad_link' => __('Slack does not accept this link'),
            'too_many_bookmarks'   => __('This channel already has as many bookmarks as Slack allows'),
            'bookmark_not_found'   => __('This bookmark is no longer in Slack'),
            'restricted_action', 'not_in_channel' => __('Slack does not let you do this in this channel'),
            'thread_locked'        => __('This thread is locked in Slack'),
            'msg_too_long'         => __('The message is too long for Slack'),
            'no_text'              => __('Write something before saving'),
            'ratelimited'          => __('Slack is busy. Try again in a minute'),
            'invalid_auth', 'not_authed', 'token_revoked', 'token_expired', 'account_inactive', 'missing_scope', 'no_permission'
                                   => __('Your Slack connection needs renewing. Connect again and try once more'),
            default                => __('Slack did not accept this. Try again later'),
        };
    }

    protected function refuse(string $sentence): never
    {
        throw ValidationException::withMessages(['slack' => $sentence]);
    }

    /**
     * The message as the screens show it, for the row to replace itself at once.
     *
     * @return array<string, mixed>|null
     */
    protected function row(SlackChannel $slackChannel, string $ts, User $viewer): ?array
    {
        $messages = SlackMessage::withTrashed()
            ->where('slack_channel_id', $slackChannel->id)
            ->where('ts', $ts)
            ->with(GetSlackChannelMessages::relations())
            ->get(GetSlackChannelMessages::COLUMNS);

        return GetSlackChannelMessages::make()->rows($slackChannel, $messages, $viewer)[0] ?? null;
    }
}
