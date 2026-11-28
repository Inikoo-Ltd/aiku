<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\NotifySlackMentions;
use App\Actions\Chat\Staff\Slack\StoreSlackDirectMessage;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Events\SlackChannelMessageChanged;
use App\Events\SlackDirectMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\Chat\SlackUser;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Posts a message to a public Slack channel as the signed-in person, with their own token, optionally as a reply in a
 * thread (also shown in the channel when asked, as Slack's "Also send to channel"), and stores Slack's answer at once. The event Slack sends back for the same ts finds the row already held, so
 * it neither adds a second one nor announces it as new again. What the person typed is sent as plain text; only a
 * mention chosen in the box (MENTION, between two U+E000 private-use characters, which no trimming removes) becomes Slack's own mention code.
 */
class SendSlackMessage
{
    use AsAction;
    use WithSlackMessageWrite;

    public const int MAX_LENGTH = 4000;

    public const int IDEMPOTENCY_SECONDS = 600;

    public const int UNCERTAIN_SECONDS = 60;

    private const string BARE_LINK = '/(<https?:\/\/[^<>|\s]+>)/u';

    public const string MENTION = '/\x{E000}(@U[A-Z0-9]{2,20}|#C[A-Z0-9]{2,20}|!here|!channel)\x{E000}/u';

    public const int EVERYONE_CONFIRM_ABOVE = 6;

    protected function writesDirect(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'text'            => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'thread_ts'       => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'also_to_channel' => ['sometimes', 'boolean'],
            'notify_everyone' => ['sometimes', 'boolean'],
            'idempotency_key' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'text.required' => __('Write a message first.'),
            'text.max'      => __('The message is too long for Slack, keep it under :max characters.', ['max' => number_format(self::MAX_LENGTH)]),
        ];
    }

    /**
     * @return array<string, mixed> the stored row as the live screen shows it, its thread parent and when it was rendered
     */
    public function handle(User $user, SlackChannel $slackChannel, string $text, ?string $threadTs, string $idempotencyKey, bool $alsoToChannel = false, bool $notifyEveryone = false): array
    {
        $isDirect = $slackChannel->direct_type !== null;
        if ($isDirect ? !self::mayWriteDirect($user, $slackChannel) : (!$slackChannel->isVisibleTo($user) || !SlackChannel::kept()->whereKey($slackChannel->id)->exists() || $slackChannel->is_archived)) {
            $this->refuse(__('Messages cannot be sent to this channel.'));
        }
        if (!SlackWriteAccess::allows($user)) {
            $this->refuse(__('You cannot send messages to Slack from aiku.'));
        }

        $text = $this->withMentions(self::toSlackText(trim($text)), $user, $slackChannel, $notifyEveryone);
        if ($text === '') {
            $this->refuse(__('Write a message first.'));
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $this->refuse(__('The message is too long for Slack, keep it under :max characters.', ['max' => number_format(self::MAX_LENGTH)]));
        }

        $rootTs      = $threadTs ? $this->threadRoot($slackChannel, $threadTs) : null;
        $isBroadcast = $rootTs !== null && $alsoToChannel;

        $client = $this->clientFor($user, standInAllowed: true, channel: $slackChannel);

        $claimKey = 'slack-send:'.$user->id.':'.$slackChannel->id.':'.$idempotencyKey;
        if (!Cache::add($claimKey, 'sending', self::IDEMPOTENCY_SECONDS)) {
            return $this->alreadySent($slackChannel, Cache::get($claimKey));
        }

        try {
            $response = $client->call('chat.postMessage', array_filter([
                'channel'   => $slackChannel->slack_id,
                'text'      => $text,
                'thread_ts'       => $rootTs,
                'reply_broadcast' => $isBroadcast,
            ]));
        } catch (SlackWriteException $exception) {
            if ($exception->slackError === 'unreachable') {
                Cache::put($claimKey, 'sending', self::UNCERTAIN_SECONDS);
            } else {
                Cache::forget($claimKey);
            }
            $this->refuse($isDirect ? self::directErrorSentence($exception->slackError, $slackChannel) : self::errorSentence($exception->slackError));
        } catch (Throwable $exception) {
            Cache::forget($claimKey);
            report($exception);
            $this->refuse(__('The message was not sent. Try again.'));
        }

        $posted = (array) Arr::get($response, 'message', []);
        $ts     = (string) (Arr::get($posted, 'ts') ?: Arr::get($response, 'ts'));
        Cache::put($claimKey, $ts, self::IDEMPOTENCY_SECONDS);

        if ($rootTs) {
            SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $rootTs)->whereNull('thread_ts')->update(['thread_ts' => $rootTs]);
        }
        $message = $this->storeOnce($slackChannel, [
            'ts' => $ts, 'thread_ts' => $rootTs, 'user' => $client->slackUserId(), 'text' => $text, ...($isBroadcast ? ['subtype' => 'thread_broadcast'] : []), ...$posted,
            ...($client->isStandIn() ? ['user' => $client->slackUserId(), 'subtype' => $isBroadcast ? 'thread_broadcast' : null] : []),
        ]);
        if ($message && $isDirect) {
            SlackDirectMessageChanged::announce($slackChannel, $message, 'new');
        }
        if ($message?->wasRecentlyCreated && !$isDirect) {
            rescue(fn () => SlackChannelMessageChanged::dispatch($slackChannel, $message, 'new'));
            rescue(fn () => NotifySlackMentions::run($message));
        }
        if ($message && !$message->sent_from_aiku) {
            $message->forceFill(['sent_from_aiku' => true])->saveQuietly();
        }

        return [...$this->payload($slackChannel, $message), 'stand_in' => $client->isStandIn()];
    }

    /**
     * The box is plain text: everything typed between < and > is shown as typed, so no mention, channel ring, user group
     * or disguised link reaches Slack. Only a bare web address in angle brackets stays a link.
     */
    public static function toSlackText(string $text): string
    {
        $parts = preg_split(self::BARE_LINK, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        return self::keepQuotes(implode('', array_map(
            fn (string $part, int $index) => $index % 2 ? $part : str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $part),
            $parts,
            array_keys($parts)
        )));
    }

    /**
     * Slack makes a quote of a line that starts with a raw >, so the box's own quote marker must not stay escaped.
     */
    public static function keepQuotes(string $escaped): string
    {
        return (string) preg_replace_callback('/^(?:&gt; ?)+/m', fn (array $match) => str_replace('&gt;', '>', $match[0]), $escaped);
    }

    /**
     * A chosen mention arrives as MENTION and is turned into Slack's code only for a live person, a channel this person
     * may see, or here/channel, which in a channel of more than EVERYONE_CONFIRM_ABOVE members (or of unknown size) needs
     * the box's confirmation. Anything else loses its separators and stays the plain text it was.
     */
    public function withMentions(string $text, User $user, SlackChannel $slackChannel, bool $notifyEveryone): string
    {
        preg_match_all(self::MENTION, $text, $found);
        $tokens = array_unique($found[1]);
        if ($tokens === []) {
            return str_replace("\u{E000}", '', $text);
        }

        $ids      = fn (string $prefix) => array_values(array_map(fn (string $token) => substr($token, 1), array_filter($tokens, fn (string $token) => $token[0] === $prefix)));
        $people   = SlackUser::whereIn('slack_id', $ids('@'))->where('is_bot', false)->where('is_deleted', false)->pluck('slack_id')->all();
        $channels = SlackChannel::visibleTo($user)->whereIn('slack_id', $ids('#'))->pluck('slack_id')->all();
        $everyone = array_intersect($tokens, ['!here', '!channel']);
        if ($everyone !== [] && !$notifyEveryone && self::needsEveryoneConfirmation($slackChannel->members()->count())) {
            throw ValidationException::withMessages(['notify_everyone' => __('Confirm that you want to notify everyone in this channel.')]);
        }

        $converted = preg_replace_callback(self::MENTION, fn (array $match) => match (true) {
            $match[1][0] === '@' && in_array(substr($match[1], 1), $people, true)   => '<'.$match[1].'>',
            $match[1][0] === '#' && in_array(substr($match[1], 1), $channels, true) => '<'.$match[1].'>',
            $match[1][0] === '!'                                                    => '<'.$match[1].'>',
            default                                                                 => $match[1],
        }, $text);

        return str_replace("\u{E000}", '', $converted);
    }

    /**
     * A channel whose members were never synced (count 0) is treated as large: the size is unknown, so ask.
     */
    public static function needsEveryoneConfirmation(int $memberCount): bool
    {
        return $memberCount === 0 || $memberCount > self::EVERYONE_CONFIRM_ABOVE;
    }

    public static function errorSentence(string $code): string
    {
        return match ($code) {
            'not_in_channel'                              => __('You are not a member of this channel in Slack. Join it in Slack, then send again.'),
            'is_archived'                                 => __('This channel is archived in Slack.'),
            'thread_not_found', 'cannot_reply_to_message' => __('This thread is no longer in Slack.'),
            'msg_too_long', 'msg_blocks_too_long'         => __('The message is too long for Slack.'),
            'ratelimited', 'rate_limited'                 => __('Slack is busy. Try again in a moment.'),
            'restricted_action', 'restricted_action_read_only_channel', 'restricted_action_thread_only_channel', 'restricted_action_non_threadable_channel', 'restricted_action_thread_locked', 'ekm_access_denied'
                                                          => __('Slack does not let you post here.'),
            'unreachable'                                 => __('Slack did not answer. Check the channel in a minute before sending again.'),
            default                                       => self::sentenceFor($code),
        };
    }

    /**
     * A DM whose other person left Slack is archived there: Verba marks it closed too, so the box says so from then on.
     */
    public static function directErrorSentence(string $code, SlackChannel $slackChannel): string
    {
        if (in_array($code, ['is_archived', 'user_disabled', 'user_not_found', 'cannot_dm_bot'], true)) {
            $slackChannel->forceFill(['is_archived' => true])->save();

            return __('This conversation is closed in Slack, the other person is no longer there.');
        }

        return match ($code) {
            'channel_not_found', 'not_in_channel' => __('This conversation is no longer in Slack.'),
            default                               => self::errorSentence($code),
        };
    }

    /**
     * Slack threads are one level deep: a reply to a reply goes to the thread's first message.
     */
    public function threadRoot(SlackChannel $slackChannel, string $threadTs): string
    {
        $parent = SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $threadTs)->first(['ts', 'thread_ts']);
        if (!$parent) {
            $this->refuse(__('This thread is no longer in Slack.'));
        }

        return $parent->thread_ts ?: $parent->ts;
    }

    /**
     * The event Slack sends for the same message can be stored by the webhook at the same moment; the loser reads the winner's row.
     *
     * @param  array<string, mixed>  $message
     */
    private function storeOnce(SlackChannel $slackChannel, array $message): ?SlackMessage
    {
        $store = fn () => $slackChannel->direct_type ? StoreSlackDirectMessage::make()->store($slackChannel, $message) : StoreSlackMessage::make()->store($slackChannel, $message);
        try {
            return $store();
        } catch (UniqueConstraintViolationException) {
            return $store();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function alreadySent(SlackChannel $slackChannel, ?string $ts): array
    {
        if (!$ts || $ts === 'sending') {
            $this->refuse(__('This message is already being sent.'));
        }

        return $this->payload($slackChannel, SlackMessage::where('slack_channel_id', $slackChannel->id)->where('ts', $ts)->first());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(SlackChannel $slackChannel, ?SlackMessage $message): array
    {
        if (!$message) {
            return ['message' => null, 'parent' => null, 'at' => microtime(true)];
        }

        return Arr::only((new SlackChannelMessageChanged($slackChannel, $message, 'new'))->broadcastWith(), ['ts', 'at', 'message', 'parent']);
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return $this->handle($request->user(), $slackChannel, $request->validated('text'), $request->validated('thread_ts'), $request->validated('idempotency_key'), (bool) $request->validated('also_to_channel', false), (bool) $request->validated('notify_everyone', false));
    }
}
