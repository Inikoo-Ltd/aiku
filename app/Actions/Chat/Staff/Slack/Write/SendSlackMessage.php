<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\NotifySlackMentions;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Events\SlackChannelMessageChanged;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\SlackMessage;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Posts a message to a public Slack channel as the signed-in person, with their own token, optionally as a reply in a
 * thread, and stores Slack's answer at once. The event Slack sends back for the same ts finds the row already held, so
 * it neither adds a second one nor announces it as new again. What the person typed is sent as plain text.
 */
class SendSlackMessage
{
    use AsAction;
    use WithSlackMessageWrite;

    public const int MAX_LENGTH = 4000;

    public const int IDEMPOTENCY_SECONDS = 600;

    public const int UNCERTAIN_SECONDS = 60;

    private const string BARE_LINK = '/(<https?:\/\/[^<>|\s]+>)/u';

    public function rules(): array
    {
        return [
            'text'            => ['required', 'string', 'max:'.self::MAX_LENGTH],
            'thread_ts'       => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
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
    public function handle(User $user, SlackChannel $slackChannel, string $text, ?string $threadTs, string $idempotencyKey): array
    {
        if (!$slackChannel->isVisibleTo($user) || !SlackChannel::kept()->whereKey($slackChannel->id)->exists() || $slackChannel->is_archived) {
            $this->refuse(__('Messages cannot be sent to this channel.'));
        }
        if (!SlackWriteAccess::allows($user)) {
            $this->refuse(__('You cannot send messages to Slack from aiku.'));
        }

        $text = self::toSlackText(trim($text));
        if ($text === '') {
            $this->refuse(__('Write a message first.'));
        }
        if (mb_strlen($text) > self::MAX_LENGTH) {
            $this->refuse(__('The message is too long for Slack, keep it under :max characters.', ['max' => number_format(self::MAX_LENGTH)]));
        }

        $rootTs = $threadTs ? $this->threadRoot($slackChannel, $threadTs) : null;

        $client = $this->clientFor($user);

        $claimKey = 'slack-send:'.$user->id.':'.$slackChannel->id.':'.$idempotencyKey;
        if (!Cache::add($claimKey, 'sending', self::IDEMPOTENCY_SECONDS)) {
            return $this->alreadySent($slackChannel, Cache::get($claimKey));
        }

        try {
            $response = $client->call('chat.postMessage', array_filter([
                'channel'   => $slackChannel->slack_id,
                'text'      => $text,
                'thread_ts' => $rootTs,
            ]));
        } catch (SlackWriteException $exception) {
            if ($exception->slackError === 'unreachable') {
                Cache::put($claimKey, 'sending', self::UNCERTAIN_SECONDS);
            } else {
                Cache::forget($claimKey);
            }
            $this->refuse(self::errorSentence($exception->slackError));
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
        $message = $this->storeOnce($slackChannel, ['ts' => $ts, 'thread_ts' => $rootTs, 'user' => $client->slackUserId(), 'text' => $text, ...$posted]);
        if ($message?->wasRecentlyCreated) {
            rescue(fn () => SlackChannelMessageChanged::dispatch($slackChannel, $message, 'new'));
            rescue(fn () => NotifySlackMentions::run($message));
        }

        return $this->payload($slackChannel, $message);
    }

    /**
     * The box is plain text: everything typed between < and > is shown as typed, so no mention, channel ring, user group
     * or disguised link reaches Slack. Only a bare web address in angle brackets stays a link.
     */
    public static function toSlackText(string $text): string
    {
        $parts = preg_split(self::BARE_LINK, $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        return implode('', array_map(
            fn (string $part, int $index) => $index % 2 ? $part : str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $part),
            $parts,
            array_keys($parts)
        ));
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
     * Slack threads are one level deep: a reply to a reply goes to the thread's first message.
     */
    private function threadRoot(SlackChannel $slackChannel, string $threadTs): string
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
        try {
            return StoreSlackMessage::make()->store($slackChannel, $message);
        } catch (UniqueConstraintViolationException) {
            return StoreSlackMessage::make()->store($slackChannel, $message);
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
        return $this->handle($request->user(), $slackChannel, $request->validated('text'), $request->validated('thread_ts'), $request->validated('idempotency_key'));
    }
}
