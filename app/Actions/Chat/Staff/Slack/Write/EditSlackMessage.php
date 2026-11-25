<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Slack\Write;

use App\Actions\Chat\Staff\Slack\GetSlackChannelMessages;
use App\Actions\Chat\Staff\Slack\StoreSlackMessage;
use App\Models\Chat\SlackChannel;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Changes the wording of the person's own message in Slack with their own token, then stores it as a
 * message_changed event would, so the earlier wording becomes a version and the echo changes nothing.
 */
class EditSlackMessage
{
    use AsAction;
    use WithSlackMessageWrite;

    public const int MAX_LENGTH = 4000;

    public function rules(): array
    {
        return [
            'ts'   => ['required', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'text' => ['required', 'string', 'max:'.(self::MAX_LENGTH * 5)],
        ];
    }

    /**
     * The edit is stamped with Slack's own edit time, or the moment before asking: never later than Slack's, so the
     * echo and anything Slack sends after it (link previews, history re-reads) still replace our copy. The text kept is
     * Slack's own (message.text), which is what the echo carries, so the echo never makes a second version.
     *
     * @return array<string, mixed>|null
     */
    public function handle(User $user, SlackChannel $slackChannel, string $ts, string $text): ?array
    {
        $this->ensureMayWrite($user, $slackChannel);
        $message = $this->liveMessage($slackChannel, $ts);
        $client  = $this->clientFor($user, standInAllowed: true);
        $this->ensureOwnMessage($message, $client);

        $slackText = self::toSlackText($text, (string) $message->text);
        if (trim($slackText) === '') {
            $this->refuse(__('Write something before saving'));
        }
        if (mb_strlen($slackText) > self::MAX_LENGTH) {
            $this->refuse(__('The message is too long for Slack'));
        }

        $askedAt  = sprintf('%.6f', microtime(true));
        $response = $this->callSlack($client, 'chat.update', ['channel' => $slackChannel->slack_id, 'ts' => $message->ts, 'text' => $slackText]);

        StoreSlackMessage::make()->handle([
            'channel'      => $slackChannel->slack_id,
            'channel_type' => 'channel',
            'subtype'      => 'message_changed',
            'message'      => array_merge(
                Arr::except((array) $message->data, ['blocks', 'edited']),
                Arr::except((array) Arr::get($response, 'message', []), ['ts', 'thread_ts', 'user']),
                [
                    'text'   => (string) (Arr::get($response, 'message.text') ?? Arr::get($response, 'text') ?? $slackText),
                    'edited' => ['user' => $client->slackUserId(), 'ts' => (string) Arr::get($response, 'message.edited.ts', $askedAt)],
                ]
            ),
        ]);

        return $this->row($slackChannel, $message->ts, $user);
    }

    /**
     * The edit box holds Slack's own text, entities included. A <…> token stays markup only when the message already
     * had it word for word (a mention, channel or link it was sent with); any other <, > and any & that is not
     * already an entity is escaped, so an edit can never add a mention, a broadcast or a link the message did not have.
     */
    public static function toSlackText(string $text, string $original): string
    {
        $known = [(string) $original, GetSlackChannelMessages::editableText($original)];
        $parts = preg_split('/(<[^<>]*>)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];

        $slackText = '';
        foreach ($parts as $index => $part) {
            $isKnownToken = $index % 2 === 1 && $part !== '<>' && (str_contains($known[0], $part) || str_contains($known[1], $part));
            $slackText .= $isKnownToken ? $part : (string) preg_replace(['/&(?!(?:amp|lt|gt);)/', '/</', '/>/'], ['&amp;', '&lt;', '&gt;'], $part);
        }

        return $slackText;
    }

    public function asController(SlackChannel $slackChannel, ActionRequest $request): array
    {
        return ['message' => $this->handle($request->user(), $slackChannel, $request->validated('ts'), $request->validated('text'))];
    }
}
