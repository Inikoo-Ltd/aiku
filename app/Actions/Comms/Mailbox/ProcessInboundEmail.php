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
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Support\Arr;
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
        $threadId = GmailMessageParser::threadId($raw);
        $headerMessageId = GmailMessageParser::header($raw, 'Message-ID');

        $mailboxAddress = Arr::get($shop->settings, 'gmail.email');
        if ($mailboxAddress && $from['address'] && strcasecmp($from['address'], $mailboxAddress) === 0) {
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

        $attachments        = $webUser ? ImportPendingGmailAttachments::make()->download($client, $gmailMessageId, $raw) : [];
        $pendingAttachments = $webUser ? 0 : count(GmailMessageParser::attachments(Arr::get($raw, 'payload', [])));

        $session = $this->findOrCreateSession($shop, $webUser, $threadId, $subject, $from);

        $message = SendChatMessage::run($session, [
            'message_text' => $body,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'attachments'  => $attachments,
            'sender_type'  => $webUser ? ChatSenderTypeEnum::USER->value : ChatSenderTypeEnum::GUEST->value,
            'sender_id'    => $webUser?->id,
        ]);

        $message->update([
            'metadata' => array_merge($message->metadata ?? [], [
                'gmail_message_id'        => $gmailMessageId,
                'gmail_header_message_id' => $headerMessageId,
                'email_subject'           => $subject,
            ], $pendingAttachments ? ['gmail_pending_attachments' => $pendingAttachments] : []),
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

    private function findOrCreateSession(Shop $shop, ?WebUser $webUser, string $threadId, ?string $subject, array $from): ChatSession
    {
        $session = ChatSession::where('shop_id', $shop->id)
            ->where('channel', ChatChannelEnum::EMAIL)
            ->where('metadata->gmail_thread_id', $threadId)
            ->first();

        if ($session) {
            if ($session->isClosed()) {
                $session->update([
                    'status'    => ChatSessionStatusEnum::ACTIVE,
                    'closed_at' => null,
                ]);
            }

            $session->update([
                'metadata' => array_merge($session->metadata ?? [], [
                    'name' => $from['name'] ?? $from['address'],
                    'email' => $from['address'],
                ]),
            ]);

            return $session;
        }

        $session = StoreChatSession::run([
            'shop_id'             => $shop->id,
            'trusted_web_user_id' => $webUser?->id,
            'language_id' => $shop->language_id,
            'priority'    => ChatPriorityEnum::NORMAL,
            'channel'     => ChatChannelEnum::EMAIL,
        ]);

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'gmail_thread_id' => $threadId,
                'email_subject'   => $subject,
                'email_from'      => $from['address'],
                'email_from_name' => $from['name'],
                'name' => $from['name'] ?? $from['address'],
                'email' => $from['address'],
            ]),
        ]);

        return $session;
    }
}
