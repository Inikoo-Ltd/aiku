<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\Chat\ChatSession\ClassifyChatSessionNoise;
use App\Actions\Chat\ChatSession\StoreChatSession;
use App\Actions\Chat\ChatSession\SuggestChatSessionCustomer;
use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\HumanResources\Employee;
use App\Models\CRM\Customer;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\HTMLSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessInboundEmail
{
    use AsAction;

    /**
     * A sender who deletes their mail before we read it leaves an id Gmail still lists and no
     * longer serves. Nothing here ever wrote a row for it, and a row is the only thing that
     * stops it being fetched again, so it came back every couple of minutes all day.
     */
    private const int GONE_TTL_DAYS = 7;

    public function handle(Shop $shop, string $gmailMessageId): ?ChatMessage
    {
        if (ChatMessage::where('metadata->gmail_message_id', $gmailMessageId)->exists()) {
            return null;
        }

        if (Cache::has($this->goneKey($shop, $gmailMessageId))) {
            return null;
        }

        $client = GmailClient::forShop($shop);

        if (! $client) {
            return null;
        }

        try {
            $raw = $client->getMessage($gmailMessageId);
        } catch (RequestException $exception) {
            if ($exception->response->status() !== 404) {
                throw $exception;
            }

            Cache::put($this->goneKey($shop, $gmailMessageId), true, now()->addDays(self::GONE_TTL_DAYS));

            return null;
        }

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

        if (! $webUser && self::isAutomatedMail($from['address'], $subject)) {
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

        $pendingAttachments = ImportPendingGmailAttachments::make()->countDeferred($client, $raw, trusted: (bool) $webUser);

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
                ['email_headers' => array_filter([
                    'list_unsubscribe' => (bool) GmailMessageParser::header($raw, 'List-Unsubscribe'),
                    'precedence'       => GmailMessageParser::header($raw, 'Precedence'),
                    'auto_submitted'   => GmailMessageParser::header($raw, 'Auto-Submitted'),
                ])],
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

        if (! $webUser) {
            SuggestChatSessionCustomer::dispatch($session);
        }

        if (! $existing && ! $webUser) {
            ClassifyChatSessionNoise::dispatch($session);
        }

        $label = $webUser ? 'aiku/imported' : 'aiku/unmatched';
        $client->addLabel($gmailMessageId, $label);

        $this->importThreadHistory($client, $session, $threadId, $mailboxAddress, $webUser);

        return $message;
    }

    private function goneKey(Shop $shop, string $gmailMessageId): string
    {
        return "gmail-message-gone:{$shop->id}:$gmailMessageId";
    }

    /**
     * The rest of the Gmail thread, so the conversation reads whole: what the customer wrote before
     * and what we answered from Gmail itself. Written straight to the table at the time Gmail has
     * for them, because these were already sent and read: nothing is mailed, broadcast or counted.
     *
     * ponytail: text and markup only, attachments of older mails stay in Gmail. An aiku reply whose
     * send job has not yet stored its gmail id would come in twice; the job runs in seconds.
     */
    public function importThreadHistory(GmailClient $client, ChatSession $session, string $threadId, ?string $mailboxAddress, ?WebUser $webUser): int
    {
        $imported = 0;

        $known = ChatMessage::where('chat_session_id', $session->id)
            ->pluck('metadata')
            ->map(fn ($metadata) => Arr::get($metadata, 'gmail_message_id'))
            ->filter()
            ->flip();

        foreach ($client->getThreadMessages($threadId) as $raw) {
            $labels = Arr::get($raw, 'labelIds', []);

            if ($known->has(Arr::get($raw, 'id')) || array_intersect($labels, ['DRAFT', 'SPAM', 'TRASH'])) {
                continue;
            }

            $from   = GmailMessageParser::fromAddress($raw)['address'];
            $isOurs = in_array('SENT', $labels, true) || ($mailboxAddress && $from && strcasecmp($from, $mailboxAddress) === 0);
            $sentAt = Carbon::createFromTimestampMs((int) Arr::get($raw, 'internalDate'));
            $html   = app(HTMLSanitizer::class)->cleanEmail(GmailMessageParser::htmlBody($raw));
            $text   = trim(strip_tags(GmailMessageParser::body($raw)));

            ChatMessage::create([
                'chat_session_id' => $session->id,
                'message_type'    => ChatMessageTypeEnum::TEXT,
                'sender_type'     => match (true) {
                    $isOurs        => ChatSenderTypeEnum::AGENT,
                    (bool) $webUser => ChatSenderTypeEnum::USER,
                    default        => ChatSenderTypeEnum::GUEST,
                },
                'sender_id'       => $isOurs ? null : $webUser?->id,
                'message_text'    => $text,
                'original_text'   => $text,
                'html_body'       => $html !== '' ? $html : null,
                'is_read'         => true,
                'created_at'      => $sentAt,
                'updated_at'      => $sentAt,
                'metadata'        => [
                    'gmail_message_id'        => Arr::get($raw, 'id'),
                    'gmail_header_message_id' => GmailMessageParser::header($raw, 'Message-ID'),
                    'email_subject'           => GmailMessageParser::header($raw, 'Subject'),
                    'gmail_thread_history'    => true,
                ],
            ]);

            $imported++;
        }

        return $imported;
    }

    /**
     * Our own mailshots land in every sibling shop's mailbox, and staff write to lists this mailbox
     * is on: neither is a customer waiting for an answer, so they never open a conversation. Matched
     * on the address rather than the domain, since customers do buy from us on our own domains.
     *
     * Staff accounts count here whichever way they reach us: a shop mailbox, an employee's work
     * address, or the account they order on with is_staff set, including the web users under it.
     */
    private function isOneOfOurs(?string $address): bool
    {
        return $address !== null && isset(self::ourOwnAddresses()[strtolower($address)]);
    }

    /**
     * Lowercase address to why it is ours, so a sweep of what already came in can say which it was.
     *
     * @return array<string, ChatIgnoreReasonEnum>
     */
    public static function ourOwnAddresses(): array
    {
        return Cache::remember('chat.our_own_email_addresses', 300, function () {
            $ours = [];

            foreach (Employee::whereNotNull('work_email')->pluck('work_email') as $email) {
                $ours[strtolower($email)] = ChatIgnoreReasonEnum::NOT_FOR_US;
            }

            foreach (Customer::where('is_staff', true)->whereNotNull('email')->pluck('email') as $email) {
                $ours[strtolower($email)] = ChatIgnoreReasonEnum::NOT_FOR_US;
            }

            $staffWebUsers = WebUser::whereNotNull('email')
                ->whereHas('customer', fn ($query) => $query->where('is_staff', true))
                ->pluck('email');

            foreach ($staffWebUsers as $email) {
                $ours[strtolower($email)] = ChatIgnoreReasonEnum::NOT_FOR_US;
            }

            // Last, so a shop mailbox keeps its own reason: these addresses are also customer and
            // web user rows of their own, and what arrives from them is our campaigns.
            foreach (Shop::whereNotNull('email')->pluck('email') as $email) {
                $ours[strtolower($email)] = ChatIgnoreReasonEnum::MARKETING;
            }

            return $ours;
        });
    }

    /**
     * Guest mail is mostly machine traffic (measured on production in September 2026: DMARC reports,
     * bounces and alerts were 59% of it), so these never become chat sessions. Only applied to
     * senders that match no customer.
     */
    public static function isAutomatedMail(?string $address, ?string $subject): bool
    {
        $localPart = str_replace(['-', '_', '.'], '', strtolower((string) strstr((string) $address, '@', true)));
        $subject   = strtolower(trim((string) $subject));

        return $localPart === 'mailerdaemon'
            || str_contains($localPart, 'noreply')
            || str_contains($localPart, 'donotreply')
            || str_contains($subject, 'report domain:')
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
