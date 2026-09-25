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
use App\Actions\Chat\ChatSession\SendOutOfHoursReply;
use App\Actions\Chat\ChatSession\FlagUrgentChatRequest;
use App\Actions\Chat\ChatSession\SummarizeLongEmail;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Group;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use App\Services\HTMLSanitizer;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;
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

    private const array MACHINE_SENDER_DOMAINS = ['luigisbox.com', 'email-abuse.amazonses.com'];

    /**
     * The row carrying the gmail id is what stops a message being taken in twice, but it is only
     * written once the message has been taken in. Two jobs starting inside that window both read
     * no row and both import, which is how one mailbox answered by two shops produced every mail
     * twice. The claim closes the window, is not scoped to a shop, and is given back when the job
     * fails so a retry is still allowed to import.
     *
     * ponytail: a claim, not an invariant. A unique index on the id would be one, at the price of
     * the losing job having already opened a chat session and its events to unpick.
     */
    public function handle(Shop $shop, string $gmailMessageId): ?ChatMessage
    {
        if (! Cache::add($this->claimKey($gmailMessageId), true, now()->addMinutes(10))) {
            return null;
        }

        try {
            return $this->import($shop, $gmailMessageId);
        } catch (Throwable $exception) {
            Cache::forget($this->claimKey($gmailMessageId));

            throw $exception;
        }
    }

    private function import(Shop $shop, string $gmailMessageId): ?ChatMessage
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
        // translation read, and what is shown if the markup is ever refused. It is purified
        // once the pictures it points at have been stored, because the purifier refuses the
        // cid scheme they arrive under and there is no second chance to resolve them.
        $rawHtml = GmailMessageParser::htmlBody($raw);
        $threadId = GmailMessageParser::threadId($raw);
        $headerMessageId = GmailMessageParser::header($raw, 'Message-ID');

        $mailboxAddress = Arr::get($shop->settings, 'gmail.email');
        // Filed away like anything else we decide not to take in: left in the inbox it would be
        // offered again by every sweep, and read as mail that never came through.
        if ($mailboxAddress && $from['address'] && strcasecmp($from['address'], $mailboxAddress) === 0) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        if ($this->isOneOfOurs($from['address'])) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        $blocked = Arr::get($shop->settings, 'gmail.blocked_senders', []);
        if ($from['address'] && in_array(strtolower($from['address']), array_map('strtolower', $blocked), true)) {
            $client->fileAway($gmailMessageId, 'aiku/spam', Arr::get($raw, 'labelIds', []));

            return null;
        }

        $webUser = $this->matchWebUser($shop, $from['address']);

        if (! $webUser && self::isAutomatedMail($from['address'], $subject)) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

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
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        // Pictures come in whoever sent them: with the markup discarded they are the only thing
        // left to look at, and mail whose images are missing reads as broken. A stranger's other
        // files still wait in Gmail until an agent has replied.
        $contentIds  = [];
        $attachments = ImportPendingGmailAttachments::make()
            ->download($client, $gmailMessageId, $raw, trusted: (bool) $webUser, contentIds: $contentIds);

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

        $html = app(HTMLSanitizer::class)->cleanEmail(
            $this->resolveInlineImages(
                $rawHtml,
                $message,
                $contentIds,
                ImportPendingGmailAttachments::make()->smallInlineImages($client, $gmailMessageId, $raw)
            )
        );

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
                'gmail_references'             => SendChatMessageByGmail::references($session->metadata ?? [], $headerMessageId),
                'name' => $from['name'] ?? $from['address'],
                'email' => $from['address'],
            ], $isAutoReply ? [] : $this->replyAllRecipients($session, $raw, $from, $mailboxAddress)),
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

        SendOutOfHoursReply::dispatch($session, $message);
        FlagUrgentChatRequest::dispatch($session);

        if (! $isAutoReply) {
            SummarizeLongEmail::dispatch($message);
        }

        $label = $webUser ? 'aiku/imported' : 'aiku/unmatched';
        $client->fileAway($gmailMessageId, $label, Arr::get($raw, 'labelIds', []));

        $this->importThreadHistory($client, $session, $threadId, $mailboxAddress, $webUser);

        return $message;
    }

    /**
     * A picture inside an email is addressed as src="cid:something", which means nothing outside
     * the mail itself: left alone the purifier drops the src and the message reads as "see the
     * photo below" with nothing below it, while the file sits detached above the text. Each one
     * is pointed at the copy we stored instead, so the mail shows the way it was written.
     *
     * The stored files are in the order they were downloaded, so position is what matches them.
     *
     * @param  array<int, string|null>  $contentIds
     * @param  array<string, string>  $smallInlineImages
     */
    private function resolveInlineImages(?string $html, ChatMessage $message, array $contentIds, array $smallInlineImages): ?string
    {
        if (! $html) {
            return $html;
        }

        $sources = $smallInlineImages;

        if (array_filter($contentIds)) {
            $files = $message->attachedFiles();

            foreach ($contentIds as $index => $contentId) {
                if ($contentId && isset($files[$index])) {
                    $sources[$contentId] = $files[$index]->getUrl();
                }
            }
        }

        foreach ($sources as $contentId => $source) {
            $html = str_ireplace(['cid:'.$contentId, 'cid:'.rawurlencode($contentId)], $source, $html);
        }

        return $html;
    }

    /**
     * Bigger accounts copy colleagues in, and any of them may be the one who writes back. The
     * answer goes to whoever wrote last, like a mail client's reply all, and everyone else the
     * thread has named is offered as a copy. An out of office names nobody new worth writing to.
     *
     * @param  array{address: ?string, name: ?string}  $from
     * @return array<string, mixed>
     */
    private function replyAllRecipients(ChatSession $session, array $raw, array $from, ?string $mailboxAddress): array
    {
        if (! $from['address']) {
            return [];
        }

        $participants = Arr::get($session->metadata, 'email_participants', []);

        $named = [
            $from,
            ...GmailMessageParser::addresses($raw, 'To'),
            ...GmailMessageParser::addresses($raw, 'Cc'),
        ];

        foreach ($named as $person) {
            $key = strtolower($person['address']);

            if ($mailboxAddress && $key === strtolower($mailboxAddress)) {
                continue;
            }

            $participants[$key] = [
                'address' => $person['address'],
                'name'    => $person['name'] ?? Arr::get($participants, "$key.name"),
            ];
        }

        return [
            'email_reply_to'      => $from['address'],
            'email_reply_to_name' => $from['name'],
            'email_participants'  => $participants,
        ];
    }

    private function claimKey(string $gmailMessageId): string
    {
        return "gmail-message-claim:$gmailMessageId";
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
     * ponytail: text and markup only, attachments of older mails stay in Gmail.
     *
     * What has already come in is asked of every message rather than of this conversation's own,
     * because a thread that was ever split across two conversations would otherwise have its
     * history written into both.
     */
    public function importThreadHistory(GmailClient $client, ChatSession $session, string $threadId, ?string $mailboxAddress, ?WebUser $webUser): int
    {
        $thread = $client->getThreadMessages($threadId);

        // Two mails of one thread arriving together used to read the same empty history and both
        // write it. Held across the reading and the writing rather than left on each message, so
        // a history that is deleted afterwards can still be fetched again.
        return Cache::lock("gmail-thread-history:$threadId", 60)->get(
            fn () => $this->writeThreadHistory($thread, $session, $mailboxAddress, $webUser)
        ) ?: 0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $thread
     */
    private function writeThreadHistory(array $thread, ChatSession $session, ?string $mailboxAddress, ?WebUser $webUser): int
    {
        $imported = 0;

        $known = ChatMessage::whereIn('metadata->gmail_message_id', array_filter(array_column($thread, 'id')))
            ->pluck('metadata')
            ->map(fn ($metadata) => Arr::get($metadata, 'gmail_message_id'))
            ->filter()
            ->flip();

        foreach ($thread as $raw) {
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
     * The accounts staff order on count here, with the web users under them. A colleague writing
     * from their own address does not: that is somebody asking customer service for something.
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
        $domain    = strtolower(substr((string) strrchr((string) $address, '@'), 1));
        $subject   = strtolower(trim((string) $subject));

        return $localPart === 'mailerdaemon'
            || collect(self::MACHINE_SENDER_DOMAINS)->contains(fn (string $machineDomain) => $domain === $machineDomain || str_ends_with($domain, '.'.$machineDomain))
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
        // A customer writing back to a finished conversation is new work nobody holds yet, so it
        // returns to the waiting queue rather than to the agent who closed it; assigning it is
        // what makes it active again.
        if ($session->isClosed() && ! $isAutoReply) {
            $session->update([
                'status'    => ChatSessionStatusEnum::WAITING,
                'closed_by' => null,
                'closed_at' => null,
            ]);

            $session->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->update([
                    'status'      => ChatAssignmentStatusEnum::RESOLVED->value,
                    'resolved_at' => now(),
                ]);
        }

        $session->update([
            'metadata' => array_merge($session->metadata ?? [], [
                'name'  => $from['name'] ?? $from['address'],
                'email' => $from['address'],
            ]),
            'is_carrier' => $session->is_carrier || (!$session->web_user_id && self::isCarrierAddress($from['address'], $session->shop?->group)),
        ]);

        return $session;
    }

    /**
     * @param  array{address: ?string, name: ?string}  $from
     */
    /**
     * A courier writing about a delivery: its domain, or any subdomain of it, is on the list.
     */
    public static function isCarrierAddress(?string $address, ?Group $group = null): bool
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $address, '@') ?: '', 1));

        return $domain !== '' && collect(self::carrierDomains($group))
            ->contains(fn (string $carrier) => $domain === $carrier || str_ends_with($domain, '.'.$carrier));
    }

    /**
     * Customer service keeps the list in the chat settings. Until they first save it the group
     * reads the list we shipped with, so an empty saved list really means no couriers.
     *
     * @return array<int, string>
     */
    public static function carrierDomains(?Group $group): array
    {
        return data_get($group?->settings, 'chat.carrier_domains') ?? config('chat.carrier_domains', []);
    }

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
            'is_carrier' => !$webUser && self::isCarrierAddress($from['address'], $shop->group),
        ]);

        return $session;
    }
}
