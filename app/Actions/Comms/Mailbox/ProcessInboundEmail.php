<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Employee;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\HTMLSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessInboundEmail
{
    use AsAction;

    public function handle(Shop $shop, string $gmailMessageId): ?ChatMessage
    {
        if (ChatMessage::where('metadata->gmail_message_id', $gmailMessageId)->exists()) {
            return null;
        }

        $client = GmailClient::forShop($shop);

        if (! $client) {
            return null;
        }

        $raw = $client->getMessage($gmailMessageId);

        $from    = GmailMessageParser::fromAddress($raw);
        $subject = GmailMessageParser::header($raw, 'Subject');
        $body    = GmailMessageParser::body($raw);

        // Kept beside the text, never instead of it: the text is what search, previews and
        // translation read, and what is shown if the markup is ever refused.
        $html = app(HTMLSanitizer::class)->cleanEmail(GmailMessageParser::htmlBody($raw));
        $threadId = GmailMessageParser::threadId($raw);
        $headerMessageId = GmailMessageParser::header($raw, 'Message-ID');

        $mailboxAddress = Arr::get($shop->settings, 'gmail.email');
        if ($mailboxAddress && $from['address'] && strcasecmp($from['address'], $mailboxAddress) === 0) {
            return null;
        }

        if ($this->isOneOfOurs($from['address'])) {
            $client->addLabel($gmailMessageId, 'aiku/filtered');

            return null;
        }

        $blocked = Arr::get($shop->settings, 'gmail.blocked_senders', []);
        if ($from['address'] && in_array(strtolower($from['address']), array_map('strtolower', $blocked), true)) {
            $client->addLabel($gmailMessageId, 'aiku/spam');

            return null;
        }

        $webUser = $this->matchWebUser($shop, $from['address']);

        if (! $webUser && $this->isAutomatedMail($from['address'], $subject)) {
            $client->addLabel($gmailMessageId, 'aiku/filtered');

            return null;
        }

        // An out of office is a machine answering, not the customer coming back. It belongs in
        // the thread so the history is honest, but it must not drag a finished conversation into
        // the waiting queue: customer service writes, the robot replies, and the chat reopens.
        $isAutoReply = $this->isAutoReply($raw);
        $existing    = $this->findSessionByThread($shop, $threadId);

        // On its own it is not a conversation at all: answering a mail we never sent leaves
        // nobody to reply to, so it is filtered rather than opened as new work.
        if (! $existing && $isAutoReply) {
            $client->addLabel($gmailMessageId, 'aiku/filtered');

            return null;
        }

        // Pictures come in whoever sent them: with the markup discarded they are the only thing
        // left to look at, and mail whose images are missing reads as broken. A stranger's other
        // files still wait in Gmail until an agent has replied.
        $attachments = ImportPendingGmailAttachments::make()
            ->download($client, $gmailMessageId, $raw, trusted: (bool) $webUser);

        $pendingAttachments = ImportPendingGmailAttachments::make()->countDeferred($raw, trusted: (bool) $webUser);

        $session = $existing
            ? $this->reuseSession($existing, $from, $isAutoReply)
            : $this->createSession($shop, $webUser, $threadId, $subject, $from);

        $message = SendChatMessage::run($session, [
            'message_text' => $body,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'attachments'  => $attachments,
            'sender_type'  => $webUser ? ChatSenderTypeEnum::USER->value : ChatSenderTypeEnum::GUEST->value,
            'sender_id'    => $webUser?->id,
        ]);

        if ($html !== '') {
            $message->update(['html_body' => $html]);
        }

        $message->update([
            'metadata' => array_merge(
                $message->metadata ?? [],
                [
                'gmail_message_id'        => $gmailMessageId,
                'gmail_header_message_id' => $headerMessageId,
                'email_subject'           => $subject,
            ],
                // Marked on the message rather than given a state of its own: it is kept so the
                // history is honest, and shown for what it is so nobody reads it as an answer.
                $isAutoReply ? ['auto_reply' => true] : [],
                $pendingAttachments ? ['gmail_pending_attachments' => $pendingAttachments] : []
            ),
        ]);

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'gmail_last_header_message_id' => $headerMessageId,
                'name' => $from['name'] ?? $from['address'],
                'email' => $from['address'],
            ]),
        ]);

        foreach ($attachments as $attachment) {
            @unlink($attachment->getPathname());
        }

        $label = $webUser ? 'aiku/imported' : 'aiku/unmatched';
        $client->addLabel($gmailMessageId, $label);

        return $message;
    }

    /**
     * Our own mailshots land in every sibling shop's mailbox, and staff write to lists this mailbox
     * is on: neither is a customer waiting for an answer, so they never open a conversation. Matched
     * on the address rather than the domain, since customers do buy from us on our own domains.
     */
    private function isOneOfOurs(?string $address): bool
    {
        if (! $address) {
            return false;
        }

        $ours = Cache::remember('chat.our_own_email_addresses', 300, function () {
            return array_map(
                'strtolower',
                array_filter(array_merge(
                    Shop::whereNotNull('email')->pluck('email')->all(),
                    Employee::whereNotNull('work_email')->pluck('work_email')->all()
                ))
            );
        });

        return in_array(strtolower($address), $ours, true);
    }

    /**
     * Guest mail is mostly machine traffic (measured on production in September 2026: DMARC reports,
     * bounces and alerts were 59% of it), so these never become chat sessions. Only applied to
     * senders that match no customer.
     */
    private function isAutomatedMail(?string $address, ?string $subject): bool
    {
        $localPart = strtolower((string) strstr((string) $address, '@', true));
        $subject   = strtolower(trim((string) $subject));

        return $localPart === 'mailer-daemon'
            || str_contains($localPart, 'noreply')
            || str_contains($localPart, 'no-reply')
            || str_starts_with($subject, 'report domain:')
            || str_starts_with($subject, 'delivery status notification');
    }

    /**
     * Mail that answers by itself says so in its headers, which is the only honest way to tell:
     * the wording of an out of office is different in every language and every mailbox.
     *
     * @param  array<string, mixed>  $raw
     */
    private function isAutoReply(array $raw): bool
    {
        $autoSubmitted = strtolower(trim((string) GmailMessageParser::header($raw, 'Auto-Submitted')));

        // RFC 3834: anything other than "no" means it was generated rather than written.
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no') {
            return true;
        }

        foreach (['X-Autoreply', 'X-Autorespond', 'X-Auto-Response-Suppress'] as $header) {
            if (GmailMessageParser::header($raw, $header)) {
                return true;
            }
        }

        return in_array(
            strtolower(trim((string) GmailMessageParser::header($raw, 'Precedence'))),
            ['auto_reply', 'bulk'],
            true
        );
    }

    private function matchWebUser(Shop $shop, ?string $email): ?WebUser
    {
        if (! $email) {
            return null;
        }

        return WebUser::where('shop_id', $shop->id)
            ->where(function ($query) use ($email) {
                $query->where('email', $email)
                    ->orWhereHas('customer', function ($customerQuery) use ($email) {
                        $customerQuery->where('email', $email);
                    });
            })
            ->first();
    }

    private function findSessionByThread(Shop $shop, string $threadId): ?ChatSession
    {
        return ChatSession::where('shop_id', $shop->id)
            ->where('channel', ChatChannelEnum::EMAIL)
            ->where('metadata->gmail_thread_id', $threadId)
            ->first();
    }

    /**
     * @param  array{address: ?string, name: ?string}  $from
     */
    private function reuseSession(ChatSession $session, array $from, bool $isAutoReply): ChatSession
    {
        if ($session->isClosed() && ! $isAutoReply) {
            $session->update([
                'status'    => ChatSessionStatusEnum::ACTIVE,
                'closed_at' => null,
            ]);
        }

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'name'  => $from['name'] ?? $from['address'],
                'email' => $from['address'],
            ]),
        ]);

        return $session;
    }

    /**
     * @param  array{address: ?string, name: ?string}  $from
     */
    private function createSession(Shop $shop, ?WebUser $webUser, string $threadId, ?string $subject, array $from): ChatSession
    {
        $session = StoreChatSession::run([
            'shop_id'             => $shop->id,
            'trusted_web_user_id' => $webUser?->id,
            'language_id'         => $shop->language_id,
            'priority'            => ChatPriorityEnum::NORMAL,
            'channel'             => ChatChannelEnum::EMAIL,
        ]);

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'gmail_thread_id' => $threadId,
                'email_subject'   => $subject,
                'email_from'      => $from['address'],
                'email_from_name' => $from['name'],
                'name'            => $from['name'] ?? $from['address'],
                'email'           => $from['address'],
            ]),
        ]);

        return $session;
    }
}
