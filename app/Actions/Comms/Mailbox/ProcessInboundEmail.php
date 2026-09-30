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
use App\Actions\Helpers\AI\AskJev;
use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatIgnoreReasonEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatPriorityEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
use App\Enums\CRM\Livechat\ChatSpamRescueKindEnum;
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
use Illuminate\Support\Str;
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

    /**
     * What is left in Gmail spam is labelled, so the spam sweep asks Gmail only for what has not
     * been read yet. Gmail search spells the slash in a label name as a dash.
     */
    public const string SPAM_CHECKED_LABEL = 'aiku/spam-checked';

    private const array MACHINE_SENDER_DOMAINS = [
        'luigisbox.com', 'email-abuse.amazonses.com',
        'brand.faire.com', 'e.faire.com', 'reply.ebay.co.uk', 'service.tiktok.com', 'shop.tiktok.com',
    ];

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
        // translation read, and what is shown if the markup is ever refused. Pictures keep the
        // cid address they arrive under; ChatMessageResource points them at the stored file.
        $rawHtml = GmailMessageParser::htmlBody($raw);
        $threadId = GmailMessageParser::threadId($raw);
        $headerMessageId = GmailMessageParser::header($raw, 'Message-ID');

        $isRescuedFromSpam = in_array('SPAM', Arr::get($raw, 'labelIds', []), true);
        $spamRescueKind    = null;
        $isPossibleScam    = false;

        if ($isRescuedFromSpam) {
            $rescue = $this->rescueFromSpam($shop, $raw, $from, $subject, $body, $threadId);

            if (is_array($rescue)) {
                $spamRescueKind = $rescue['kind'];
                $isPossibleScam = $rescue['is_possible_scam'];
            } elseif ($rescue === false) {
                $client->fileAway($gmailMessageId, self::SPAM_CHECKED_LABEL, markRead: false);

                return null;
            } elseif ($rescue === null) {
                Cache::put(self::leftInSpamKey($shop, $gmailMessageId), true, now()->addHour());

                return null;
            }
        }

        $mailboxAddress = Arr::get($shop->settings, 'gmail.email');
        // Filed away like anything else we decide not to take in: left in the inbox it would be
        // offered again by every sweep, and read as mail that never came through.
        if ($mailboxAddress && $from['address'] && strcasecmp($from['address'], $mailboxAddress) === 0) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        $isColleague = $this->isColleague($from['address']);

        // A colleague answering inside a customer's thread is not the customer writing back: the
        // reply would reopen the conversation and turn our answers towards the colleague.
        if (($this->isOneOfOurs($from['address']) && ! $isColleague)
            || ($isColleague && self::isColleagueCircular($raw, $mailboxAddress, $subject))
            || ($isColleague && $this->findSessionByThread($shop, $threadId)?->is_colleague === false)) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        $senderLabel = $this->labelForSender($shop, $from['address']);
        if ($senderLabel) {
            $client->fileAway($gmailMessageId, $senderLabel, Arr::get($raw, 'labelIds', []), markRead: false);

            return null;
        }

        $blocked = Arr::get($shop->settings, 'gmail.blocked_senders', []);
        if ($from['address'] && in_array(strtolower($from['address']), array_map('strtolower', $blocked), true)) {
            $client->fileAway($gmailMessageId, 'aiku/spam', Arr::get($raw, 'labelIds', []));

            return null;
        }

        // Order, shipping and payout notices from the marketplaces are work for whoever runs those
        // portals, not a conversation. They wait unread under their own label in Gmail. Buyers'
        // messages come from another address and still reach the inbox.
        if (self::isMarketplaceNotice($from['address'])) {
            $client->fileAway($gmailMessageId, 'Marketplaces', Arr::get($raw, 'labelIds', []), markRead: false);

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
        $isAutoReply = self::isAutoReply($raw);
        $existing    = $this->findSessionByThread($shop, $threadId);

        // On its own it is not a conversation at all: answering a mail we never sent leaves
        // nobody to reply to, so it is filtered rather than opened as new work.
        if (! $existing && $isAutoReply) {
            $client->fileAway($gmailMessageId, 'aiku/filtered', Arr::get($raw, 'labelIds', []));

            return null;
        }

        // Pictures come in whoever sent them: with the markup discarded they are the only thing
        // left to look at, and mail whose images are missing reads as broken. A stranger's other
        // files still wait in Gmail until an agent has replied, and so do the files of anything that
        // came out of Gmail spam: those wait until an agent asks for them.
        $contentIds  = [];
        $attachments = ImportPendingGmailAttachments::make()
            ->download($client, $gmailMessageId, $raw, trusted: $webUser && ! $isRescuedFromSpam, contentIds: $contentIds);

        $pendingAttachments = ImportPendingGmailAttachments::make()->countDeferred($client, $raw, trusted: $webUser && ! $isRescuedFromSpam);

        $session = $existing
            ? $this->reuseSession($existing, $from, $isAutoReply)
            : $this->createSession($shop, $webUser, $threadId, $subject, $from, $isColleague);

        $message = SendChatMessage::run($session, [
            'message_text' => $body,
            'message_type' => ChatMessageTypeEnum::TEXT->value,
            'attachments'  => $attachments,
            'attachment_content_ids' => $contentIds,
            'sender_type'  => $webUser ? ChatSenderTypeEnum::USER->value : ChatSenderTypeEnum::GUEST->value,
            'sender_id'    => $webUser?->id,
        ]);

        $html = app(HTMLSanitizer::class)->cleanEmail(
            $this->embedSmallInlineImages(
                $rawHtml,
                ImportPendingGmailAttachments::make()->smallInlineImages($client, $gmailMessageId, $raw)
            )
        );

        if ($html !== '') {
            $message->update(['html_body' => $html]);
        }

        $message->update([
            'is_rescued_from_spam' => $isRescuedFromSpam,
            'spam_rescue_kind'     => $spamRescueKind,
            'is_possible_scam'     => $isPossibleScam,
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

        if (! $isAutoReply) {
            SendOutOfHoursReply::dispatch($session, $message);
            FlagUrgentChatRequest::dispatch($session);
            SummarizeLongEmail::dispatch($message);
        }

        $label = $webUser ? 'aiku/imported' : 'aiku/unmatched';
        $client->fileAway($gmailMessageId, $label, Arr::get($raw, 'labelIds', []));

        $this->importThreadHistory($client, $session, $threadId, $mailboxAddress, $webUser);

        return $message;
    }

    /**
     * A picture too small to be stored as a file is written into the body itself.
     *
     * @param  array<string, string>  $smallInlineImages  Content-ID => data uri
     */
    private function embedSmallInlineImages(?string $html, array $smallInlineImages): ?string
    {
        if (! $html) {
            return $html;
        }

        foreach ($smallInlineImages as $contentId => $source) {
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

    public static function leftInSpamKey(Shop $shop, string $gmailMessageId): string
    {
        return "gmail-message-left-in-spam:{$shop->id}:$gmailMessageId";
    }

    /**
     * Gmail's spam folder holds the odd customer or prospect among hundreds of junk mails.
     * Customers who have bought from us and replies to our own conversations always come in;
     * anybody can register, so a customer who never bought is asked about like a stranger.
     * Newsletters and machines never do. A stranger's email is shown to Jev once, which says what
     * kind of email it is and whether it takes a common scam form, and it comes in when a customer
     * request or a prospect is likely enough and neither answer takes it for a scam. Whatever comes
     * in, customers included, is tagged when a scam form is probable. The rest stays in Gmail's
     * spam, where Gmail deletes it, labelled so it is never read again. Null means there was no
     * answer, and the question is asked again an hour later rather than on every sweep.
     *
     * @param  array{address: ?string, name: ?string}  $from
     * @return array{kind: ?ChatSpamRescueKindEnum, is_possible_scam: bool}|false|null
     */
    private function rescueFromSpam(Shop $shop, array $raw, array $from, ?string $subject, ?string $body, string $threadId): array|false|null
    {
        $state   = "From: {$from['name']} <{$from['address']}>\nSubject: $subject\n\n".mb_substr(trim(strip_tags((string) $body)), 0, 4000);
        $scamForm = ['type' => 'choice', 'instructions' => 'Is this email one of these common scam forms?', 'criteria' => ChatSpamRescueKindEnum::scamForms()];

        if ($this->matchWebUser($shop, $from['address'])?->customer?->stats?->number_invoices_type_invoice || $this->findSessionByThread($shop, $threadId)) {
            $answers = AskJev::run($state, ['scam_form' => $scamForm]);

            return ['kind' => null, 'is_possible_scam' => $this->isProbablyScam($answers)];
        }

        if (GmailMessageParser::header($raw, 'List-Unsubscribe') || self::isAutomatedMail($from['address'], $subject)) {
            return false;
        }

        $answers = AskJev::run($state, [
            'kind'      => [
                'type'         => 'choice',
                'instructions' => 'This email reached the customer service mailbox of a wholesale giftware supplier that sells to shops, including dropshipping. What kind of email is it?',
                'criteria'     => ChatSpamRescueKindEnum::definitions(),
            ],
            'scam_form' => $scamForm,
        ]);

        $kind = ChatSpamRescueKindEnum::tryFrom((string) Arr::get($answers, 'kind.choice'));

        if (! $kind) {
            return null;
        }

        $wanted = collect(ChatSpamRescueKindEnum::cases())
            ->filter(fn (ChatSpamRescueKindEnum $case) => $case->isWanted())
            ->sum(fn (ChatSpamRescueKindEnum $case) => (float) Arr::get($answers, "kind.probabilities.$case->value", 0));

        $looksLikeScam = $kind === ChatSpamRescueKindEnum::SCAM || Arr::get($answers, 'scam_form.choice', 'none') !== 'none';

        if ($wanted < config('chat.spam_rescue_min_probability') || $looksLikeScam) {
            return false;
        }

        return ['kind' => $kind, 'is_possible_scam' => $this->isProbablyScam($answers)];
    }

    /**
     * Only when a scam is probable, not merely possible: a warning on every email would soon be
     * read by nobody.
     *
     * @param  array<string, mixed>|null  $answers
     */
    private function isProbablyScam(?array $answers): bool
    {
        $none = Arr::get($answers, 'scam_form.probabilities.none');

        return $none !== null && 1 - (float) $none >= config('chat.spam_rescue_scam_tag_probability');
    }

    private function claimKey(string $gmailMessageId): string
    {
        return "gmail-message-claim:$gmailMessageId";
    }

    private function goneKey(Shop $shop, string $gmailMessageId): string
    {
        return "gmail-message-gone:{$shop->id}:$gmailMessageId";
    }

    private function labelForSender(Shop $shop, ?string $address): ?string
    {
        if (! $address) {
            return null;
        }

        $labeledSenders = Arr::get($shop->settings, 'gmail.labeled_senders') ?? [];
        $address        = strtolower($address);

        return $labeledSenders[$address] ?? $labeledSenders['@'.Str::after($address, '@')] ?? null;
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
            // Files of earlier mails are fetched only when an agent asks, so a long thread does
            // not download everything it ever carried.
            $pending = ImportPendingGmailAttachments::make()->countImportable($raw);

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
                ] + ($pending ? ['gmail_pending_attachments' => $pending] : []),
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
     * The accounts staff order on count here, with the web users under them. Staff mostly order
     * on their work address, so what they write in person is let through by isColleagueCircular
     * to the Colleagues folder.
     */
    private function isOneOfOurs(?string $address): bool
    {
        return $address !== null && isset(self::ourOwnAddresses()[strtolower($address)]);
    }

    private function isColleague(?string $address): bool
    {
        return $address !== null && (self::ourOwnAddresses()[strtolower($address)] ?? null) === ChatIgnoreReasonEnum::NOT_FOR_US;
    }

    /**
     * What colleagues send to the shop mailboxes is mostly circulars: stock news to every shop,
     * newsletters, lists, notifications. Only mail written to this mailbox, and to no other of
     * ours, is somebody asking customer service for something.
     *
     * @param  array<string, mixed>  $raw
     */
    public static function isColleagueCircular(array $raw, ?string $mailboxAddress, ?string $subject): bool
    {
        if (GmailMessageParser::header($raw, 'List-Id')
            || GmailMessageParser::header($raw, 'List-Unsubscribe')
            || strtolower(trim((string) GmailMessageParser::header($raw, 'Precedence'))) === 'list'
            || self::isAutoReply($raw)
            || self::isAutomatedMail(GmailMessageParser::fromAddress($raw)['address'], $subject)) {
            return true;
        }

        $recipients = collect([...GmailMessageParser::addresses($raw, 'To'), ...GmailMessageParser::addresses($raw, 'Cc')])
            ->map(fn (array $address) => strtolower($address['address']))
            ->unique();

        $ourMailboxes = $recipients->filter(fn (string $address) => (self::ourOwnAddresses()[$address] ?? null) === ChatIgnoreReasonEnum::MARKETING
            || ($mailboxAddress && $address === strtolower($mailboxAddress)));

        return ! $mailboxAddress
            || ! $recipients->contains(strtolower($mailboxAddress))
            || $ourMailboxes->count() > 1;
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
    public static function isAutoReply(array $raw): bool
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
    public static function isMarketplaceNotice(?string $address): bool
    {
        $domain = mb_strtolower((string) substr(strrchr((string) $address, '@') ?: '', 1));

        return $domain !== '' && in_array($domain, config('chat.marketplace_notice_domains', []), true);
    }

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

    private function createSession(Shop $shop, ?WebUser $webUser, string $threadId, ?string $subject, array $from, bool $isColleague): ChatSession
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
            'is_carrier'   => !$webUser && self::isCarrierAddress($from['address'], $shop->group),
            'is_colleague' => $isColleague,
        ]);

        return $session;
    }
}
