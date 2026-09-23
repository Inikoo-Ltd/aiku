<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\MetaChatSession\SendMetaChatGreeting;
use App\Actions\Chat\Reports\IsWithinWorkingHours;
use App\Actions\Comms\Mailbox\ProcessInboundEmail;
use App\Actions\Comms\Mailbox\SendChatMessageByGmail;
use App\Enums\CRM\Livechat\ChatAutomationKindEnum;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatNoiseVerdictEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\Chat\MetaChatSession;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A message that arrives while the shop is closed is answered with when it opens again, from
 * the same work schedule the website chat shows, so the customer knows they have been heard
 * and when to expect a person. Website chat, WhatsApp and email alike.
 *
 * Once per wait: nothing more is sent until an agent has answered, so a customer who writes
 * five messages overnight gets one reply, and one who comes back next week gets another. An
 * agent who answered within the hour is taken to be still there, past closing or not. The
 * same fixed words every time, sent as the system, never written by a model. Unique per
 * conversation, so a burst of messages cannot race into several replies.
 *
 * Email answers only a person (RFC 3834): never mail that says it was generated, came from a
 * list or a machine address, one of our own staff, or anything put aside as noise, it is
 * marked Auto-Submitted so the other side's auto-responder stays quiet, and one address gets
 * at most one a day, whatever conversation it writes in.
 */
class SendOutOfHoursReply implements ShouldBeUnique
{
    use AsAction;

    public const string SENT_KEY = 'out_of_hours_replied_at';

    public function getJobUniqueId(ChatSession|MetaChatSession $chatSession, ?ChatMessage $trigger = null): string
    {
        return class_basename($chatSession).':'.$chatSession->id;
    }

    public function handle(ChatSession|MetaChatSession $chatSession, ?ChatMessage $trigger = null): bool
    {
        $chatSession->refresh();
        $shop = $chatSession->shop;

        if (!config('chat.out_of_hours_reply')
            || !$shop
            || $chatSession->is_spam
            || $chatSession->is_rubbish
            || IsWithinWorkingHours::run($shop, now())
            || $this->lastAgentMessageAt($chatSession)?->gt(now()->subHour())
            || $this->alreadyRepliedThisWait($chatSession)) {
            return false;
        }

        if ($chatSession instanceof MetaChatSession) {
            return SendMetaChatGreeting::run($chatSession, $this->text($chatSession), self::SENT_KEY, false);
        }

        $byEmail = $chatSession->channel === ChatChannelEnum::EMAIL;

        if ($byEmail && (!$trigger || !$this->mayAnswerEmail($chatSession, $trigger))) {
            return false;
        }

        $chatSession->update([
            'metadata' => array_merge($chatSession->metadata ?? [], [self::SENT_KEY => now()->toISOString()]),
        ]);

        $reply = SendChatMessage::run($chatSession, [
            'message_text' => $this->text($chatSession),
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'sender_type'  => ChatSenderTypeEnum::SYSTEM->value,
        ]);

        $reply->update(['metadata' => array_merge($reply->metadata ?? [], ['automated' => ChatAutomationKindEnum::OUT_OF_HOURS->value], $byEmail ? ['auto_submitted' => true] : [])]);

        if ($byEmail) {
            SendChatMessageByGmail::dispatch($reply);
        }

        return true;
    }

    private function mayAnswerEmail(ChatSession $chatSession, ChatMessage $trigger): bool
    {
        $headers       = (array) data_get($trigger->metadata, 'email_headers', []);
        $autoSubmitted = strtolower(trim((string) Arr::get($headers, 'auto_submitted')));
        $from          = strtolower(trim((string) data_get($chatSession->metadata, 'email_from')));

        if (data_get($trigger->metadata, 'auto_reply')
            || Arr::get($headers, 'list_unsubscribe')
            || in_array(strtolower((string) Arr::get($headers, 'precedence')), ['bulk', 'list', 'junk'], true)
            || ($autoSubmitted !== '' && $autoSubmitted !== 'no')
            || !str_contains($from, '@')
            || ProcessInboundEmail::isAutomatedMail($from, (string) data_get($chatSession->metadata, 'email_subject'))
            || ClassifyChatSessionNoise::isStaffEmail($from)
            || ChatNoiseVerdictEnum::tryFrom((string) $chatSession->noise_verdict)?->isNoise()) {
            return false;
        }

        return Cache::add('chat-out-of-hours-email:'.sha1($from), true, now()->addDay());
    }

    private function lastAgentMessageAt(ChatSession|MetaChatSession $chatSession): ?Carbon
    {
        return $chatSession->last_agent_message_at ? Carbon::parse($chatSession->last_agent_message_at) : null;
    }

    private function alreadyRepliedThisWait(ChatSession|MetaChatSession $chatSession): bool
    {
        $repliedAt = data_get($chatSession->metadata, self::SENT_KEY);

        if (!$repliedAt) {
            return false;
        }

        return !$this->lastAgentMessageAt($chatSession)?->gt(Carbon::parse($repliedAt));
    }

    private function text(ChatSession|MetaChatSession $chatSession): string
    {
        $shop   = $chatSession->shop;
        $locale = $shop->language?->code;
        $next   = IsWithinWorkingHours::make()->nextOpening($shop, now());

        if (!$next) {
            return __('Thank you for your message. We are closed at the moment and will reply as soon as we are back.', [], $locale);
        }

        return __('Thank you for your message. We are closed at the moment and will reply from :time on :day. Please tell us how we can help and we will pick it up first thing.', [
            'time' => $next['opens']->format('H:i'),
            'day'  => $next['opens']->locale($locale ?? 'en')->isoFormat('dddd D MMMM'),
        ], $locale);
    }
}
