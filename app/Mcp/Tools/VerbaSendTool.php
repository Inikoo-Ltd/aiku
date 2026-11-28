<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 28 Nov 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Chat\Staff\SendStaffMessage;
use App\Actions\Chat\Staff\Slack\Write\SendSlackMessage;
use App\Actions\Chat\Staff\UI\ShowStaffSlackChannels;
use App\Actions\Tasks\GetAikuAssistant;
use App\Models\Chat\SlackChannel;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Sends through the same actions as the Verba box (SendStaffMessage, SendSlackMessage with its Slack write access, opt-in
 * and DM rules), never a path of its own. Not written to the AI changes log, and LogMcpRequest keeps neither text nor
 * request_text, because what staff write to each other stays out of every log (INI-427).
 */
#[Description('Sends a message in the user\'s name to one of their Verba conversations or V·Desks (an id from verba-tool). Without confirm=true nothing is sent: a preview comes back with where it goes, whether it goes to Slack, the exact text and a confirm_token; show it to the user and send only after they say yes in their own words, calling again with the same arguments, confirm=true, that confirm_token and their request text. A token sends once, only the message it was previewed for. reply_to answers a Verba message (its id) and thread_ts replies in a Slack thread. Plain text only: @mentions of Slack people, @here and @channel are not turned into notifications. Only where the user may write in Verba: a conversation they are still in, or a V·Desk or Slack DM they may write in (Slack rules apply: private Slack V·Desks are read only, DMs need their Slack connected with the private opt-in).')]
class VerbaSendTool extends Tool
{
    use WithVerbaConversation;

    private const string TOKEN_PREFIX = 'mcp-verba-send-token:';

    private const int TOKEN_MINUTES = 30;

    private const int SENDS_PER_MINUTE = 20;

    public function handle(Request $request): Response
    {
        $request->validate([
            'conversation' => ['required', 'string', 'max:40'],
            'text'         => ['required', 'string', 'max:'.SendSlackMessage::MAX_LENGTH],
            'reply_to'     => ['sometimes', 'nullable', 'integer'],
            'thread_ts'    => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,12}\.\d{1,8}$/'],
            'confirm'       => ['sometimes', 'boolean'],
            'confirm_token' => ['required_if:confirm,true', 'nullable', 'string', 'max:64'],
            'request_text'  => ['required_if:confirm,true', 'nullable', 'string', 'max:4000'],
        ]);

        /** @var User $user */
        $user         = $request->user();
        $conversation = $this->verbaConversation($user, (string) $request->string('conversation'));
        if (!$conversation) {
            return Response::error(self::NOT_FOUND);
        }

        $text = trim(str_replace("\u{E000}", '', (string) $request->get('text')));
        if ($conversation instanceof StaffConversation) {
            $text = trim(strip_tags($text));
        }
        if ($text === '') {
            return Response::error('Write a message first. Nothing was sent.');
        }

        if ($request->boolean('confirm')) {
            $refusal = $this->consumeConfirmToken($request, $user, $conversation, $text);
            if ($refusal) {
                return $refusal;
            }
        }

        return $conversation instanceof StaffConversation
            ? $this->toConversation($conversation, $user, $text, $request)
            : $this->toSlack($conversation, $user, $text, $request);
    }

    private function toConversation(StaffConversation $conversation, User $user, string $text, Request $request): Response
    {
        if (!$conversation->isActiveParticipant($user)) {
            return Response::error('This user is no longer in that conversation, so they cannot write in it. Nothing was sent.');
        }
        if ($conversation->type === 'dm' && $conversation->participants()->where('username', GetAikuAssistant::USERNAME)->exists()) {
            return Response::error('The Aiku assistant\'s messages cannot be answered. Nothing was sent.');
        }
        if (mb_strlen($text) > 5000) {
            return Response::error('The message is too long, keep it under 5,000 characters. Nothing was sent.');
        }

        $replyTo = $request->get('reply_to');
        if ($replyTo && !$conversation->messages()->whereKey($replyTo)->when($conversation->leftAtFor($user), fn ($query, $leftAt) => $query->where('created_at', '<=', $leftAt))->exists()) {
            return Response::error('Message '.$replyTo.' is not in this conversation. Nothing was sent.');
        }

        $to = $conversation->name ?: $conversation->participants()->where('users.id', '!=', $user->id)->get()->map(fn (User $participant) => $participant->chatName())->implode(', ');
        if (!$request->boolean('confirm')) {
            return $this->preview($request, $user, $conversation, ['to' => $to, 'conversation' => $conversation->ulid, 'via' => 'Verba', 'reply_to' => $replyTo, 'text' => $text]);
        }

        $message = SendStaffMessage::make()->handle($conversation, $user, ['body' => $text, 'parent_id' => $replyTo]);

        return Response::json(['sent' => true, 'to' => $to, 'conversation' => $conversation->ulid, 'message_id' => $message->id]);
    }

    private function toSlack(SlackChannel $channel, User $user, string $text, Request $request): Response
    {
        $to       = $channel->direct_type ? ShowStaffSlackChannels::directName($channel, $user) : '#'.$channel->name;
        $threadTs = $request->get('thread_ts');

        $mayWrite = $channel->direct_type ? SendSlackMessage::mayWriteDirect($user, $channel) : SendSlackMessage::mayWrite($user, $channel);
        if (!$mayWrite) {
            return Response::error('This user cannot write in '.self::verbaId($channel).' from Verba'.($channel->is_private && !$channel->direct_type ? ': private Slack V·Desks are read only' : ($channel->direct_type ? ': Slack DMs need their own Slack connected with the private opt-in, on their Verba profile' : '')).'. Nothing was sent.');
        }

        if (!$request->boolean('confirm')) {
            return $this->preview($request, $user, $channel, ['to' => $to, 'conversation' => self::verbaId($channel), 'via' => 'Slack, as the user', 'thread_ts' => $threadTs, 'text' => $text]);
        }

        try {
            $sent = SendSlackMessage::make()->handle($user, $channel, $text, $threadTs, substr(hash('sha256', (string) $request->get('confirm_token')), 0, 40));
        } catch (ValidationException $exception) {
            return Response::error(implode(' ', $exception->validator->errors()->all()).' Nothing was sent.');
        }

        return Response::json(array_filter([
            'sent'         => true,
            'to'           => $to,
            'conversation' => self::verbaId($channel),
            'ts'           => $sent['message']['ts'] ?? $sent['ts'] ?? null,
            'stand_in'     => !empty($sent['stand_in']) ? 'sent through the Verba app with the user\'s name, as their Slack is not connected' : null,
        ], fn ($value) => $value !== null));
    }

    /**
     * A send needs the token of a preview of exactly that message, used once: the assistant cannot send what the user
     * was not shown, a retried confirm sends nothing twice, and each preview is its own message for Slack's idempotency.
     *
     * @param  array<string, mixed>  $preview
     */
    private function preview(Request $request, User $user, StaffConversation|SlackChannel $conversation, array $preview): Response
    {
        $token = Str::random(40);
        Cache::put(self::TOKEN_PREFIX.$token, $this->fingerprint($request, $user, $conversation, $preview['text']), now()->addMinutes(self::TOKEN_MINUTES));

        return Response::json([
            'preview'       => array_filter($preview, fn ($value) => $value !== null),
            'confirm_token' => $token,
            'next'          => 'Nothing was sent. Show the user where it goes and the exact text; only after they say yes, call again with the same arguments, confirm=true, this confirm_token and their request text. The token works once, for '.self::TOKEN_MINUTES.' minutes.',
        ]);
    }

    private function consumeConfirmToken(Request $request, User $user, StaffConversation|SlackChannel $conversation, string $text): ?Response
    {
        $rateKey = 'mcp-verba-send:'.$user->id;
        if (RateLimiter::tooManyAttempts($rateKey, self::SENDS_PER_MINUTE)) {
            return Response::error('Too many messages in a minute; wait a little. Nothing was sent.');
        }

        $expected = Cache::pull(self::TOKEN_PREFIX.$request->get('confirm_token'));
        if (!$expected || !hash_equals($expected, $this->fingerprint($request, $user, $conversation, $text))) {
            return Response::error('That confirm_token is unknown, used or for another message. Preview again (without confirm), show the user, and confirm with the new token. Nothing was sent.');
        }
        RateLimiter::hit($rateKey);

        return null;
    }

    private function fingerprint(Request $request, User $user, StaffConversation|SlackChannel $conversation, string $text): string
    {
        return hash('sha256', implode("\0", [$user->id, self::verbaId($conversation), $request->get('reply_to'), $request->get('thread_ts'), $text]));
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'conversation' => $schema->string()->description('Id from verba-tool: a Verba conversation id or slack-<number>')->required(),
            'text'         => $schema->string()->description('The message, plain text, exactly as the user approved it')->required(),
            'reply_to'     => $schema->integer()->description('Verba only: id of the message to answer'),
            'thread_ts'    => $schema->string()->description('Slack only: ts of the thread to reply in'),
            'confirm'      => $schema->boolean()->description('true only after the user has seen the preview and said yes'),
            'confirm_token' => $schema->string()->description('The confirm_token the preview of this exact message returned; required with confirm=true'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required with confirm=true. Never logged'),
        ];
    }
}
