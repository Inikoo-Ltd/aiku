<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastRealtimeChat;
use App\Models\Chat\ChatAgent;
use App\Actions\Chat\ChatSession\GetChatMediaContents;
use App\Actions\Chat\ChatSession\TranslateChatMessage;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\ReleaseWhenGmailRateLimited;
use Illuminate\Support\Arr;
use RuntimeException;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Laravel\Facade as Sentry;
use Throwable;

class SendChatMessageByGmail
{
    use AsAction;

    public string $jobQueue = 'urgent';

    public const int MAX_ATTACHED_BYTES = 18 * 1024 * 1024;

    /**
     * @return array<int, object>
     */
    public function getJobMiddleware(): array
    {
        return [new ReleaseWhenGmailRateLimited()];
    }

    public function handle(ChatMessage $chatMessage): void
    {
        $session = $chatMessage->chatSession;

        if (! $session || $session->channel !== ChatChannelEnum::EMAIL) {
            return;
        }

        if (! Arr::get($session->metadata, 'email_reply_to') && ! Arr::get($session->metadata, 'email_from')) {
            return;
        }

        $threadId = Arr::get($session->metadata, 'gmail_thread_id');

        $client = GmailClient::forShop($session->shop);

        if (! $client) {
            if (filled(Arr::get($session->shop->settings, 'gmail.revoked_at'))) {
                throw new RuntimeException("Reply {$chatMessage->id} not emailed: Google stopped the {$session->shop->slug} mailbox connection, it needs reconnecting");
            }

            return;
        }

        $this->translateAgentReply($chatMessage);

        $messageId = $this->newHeaderMessageId($session);
        $recipient = Arr::get($session->metadata, 'email_reply_to')
            ? ['address' => Arr::get($session->metadata, 'email_reply_to'), 'name' => Arr::get($session->metadata, 'email_reply_to_name')]
            : ['address' => Arr::get($session->metadata, 'email_from'), 'name' => Arr::get($session->metadata, 'email_from_name')];

        [$raw, $sentHtml] = $this->buildRawMessage($session, $chatMessage, $messageId);

        $result = $client->send($raw, $threadId);

        $session->refresh();

        // A conversation we started has no thread until Gmail gives it one. Without it kept here
        // the customer's reply matches nothing and opens a second conversation beside this one.
        // The Gmail thread id only groups mail inside our own mailbox. The customer's mail client
        // threads by these headers, so every mail we send must name the one before it, agent or
        // customer alike, or three answers in a row land as three separate emails.
        $session->update([
            'metadata' => array_merge($session->metadata ?? [], array_filter([
                'gmail_thread_id'              => $threadId ?: Arr::get($result, 'threadId'),
                'gmail_last_header_message_id' => $messageId,
                'gmail_references'             => $this->references($session->metadata ?? [], $messageId),
            ])),
        ]);

        // The inbox shows a reply as the mail the customer got, in the language and with the
        // signature and pictures it went out with, not as the text the agent typed.
        $chatMessage->update([
            'html_body' => $sentHtml,
            'metadata'  => array_merge($chatMessage->metadata ?? [], [
                'gmail_message_id' => Arr::get($result, 'id'),
                'email_to'         => [$recipient],
            ]),
        ]);

        BroadcastRealtimeChat::dispatch($chatMessage);
    }

    private function translateAgentReply(ChatMessage $chatMessage): void
    {
        if ($chatMessage->sender_type !== ChatSenderTypeEnum::AGENT) {
            return;
        }

        try {
            TranslateChatMessage::run($chatMessage->id);
        } catch (Throwable $e) {
            Sentry::captureException($e);
        }

        $chatMessage->refresh();
    }

    public static function references(array $metadata, ?string ...$append): array
    {
        return array_values(array_unique(array_filter([
            ...Arr::get($metadata, 'gmail_references', []),
            Arr::get($metadata, 'gmail_last_header_message_id'),
            ...$append,
        ])));
    }

    /**
     * Everyone the thread has named except whoever the answer goes to, less those the agent
     * unticked. Fixed when the agent sends, so a colleague who writes in while the mail waits
     * in the queue is not copied into an answer nobody chose to send them.
     *
     * @param  array<int, string>  $excluded
     * @return array<int, array{address: string, name: ?string}>
     */
    public static function copyRecipients(ChatSession $session, array $excluded = []): array
    {
        $metadata       = $session->metadata ?? [];
        $recipient      = strtolower((string) (Arr::get($metadata, 'email_reply_to') ?: Arr::get($metadata, 'email_from')));
        $excluded       = array_map('strtolower', $excluded);
        $mailboxAddress = Arr::get($session->shop->settings, 'gmail.email');

        return array_values(array_filter(
            Arr::get($metadata, 'email_participants', []),
            fn (array $person, string $key) => $key !== $recipient
                && ! in_array($key, $excluded, true)
                && ! self::deliversToMailbox($key, $mailboxAddress),
            ARRAY_FILTER_USE_BOTH
        ));
    }

    /**
     * Every address on the mailbox's own domain is delivered into the mailbox itself: the shop's
     * older addresses (ahoj@awgifts.sk, szia@awgifts.hu) and the staff ones alike. Copying one in
     * sends the answer straight back to the shop's own Gmail (HELP-3695).
     */
    public static function deliversToMailbox(?string $address, ?string $mailboxAddress): bool
    {
        if (! $address || ! $mailboxAddress) {
            return false;
        }

        return strcasecmp(Str::after($address, '@'), Str::after($mailboxAddress, '@')) === 0;
    }

    private function newHeaderMessageId($session): string
    {
        $domain = Str::after((string) Arr::get($session->shop->settings, 'gmail.email'), '@') ?: 'aiku.io';

        return '<'.Str::ulid().'@'.$domain.'>';
    }

    /**
     * @return array{0: string, 1: string}  the raw mail, and the html of its body as the customer reads it
     */
    private function buildRawMessage($session, ChatMessage $chatMessage, string $messageId): array
    {
        $metadata = $session->metadata ?? [];

        $mailboxAddress = Arr::get($session->shop->settings, 'gmail.email');
        $toAddress      = Arr::get($metadata, 'email_reply_to') ?: Arr::get($metadata, 'email_from');
        $toName         = Arr::get($metadata, 'email_reply_to') ? Arr::get($metadata, 'email_reply_to_name') : Arr::get($metadata, 'email_from_name');
        $subject        = Arr::get($metadata, 'email_subject') ?? '';
        $replyToHeader  = Arr::get($metadata, 'gmail_last_header_message_id');

        // Only an answer is prefixed: the first mail of a conversation we started replies to
        // nothing, and a subject reading "Re:" out of the blue looks like a lost thread.
        if ($replyToHeader && ! str_starts_with(trim($subject), 'Re:')) {
            $subject = 'Re: '.$subject;
        }

        if (! self::headerAddress($toAddress)) {
            throw new RuntimeException("Reply {$chatMessage->id} not emailed: the conversation's address \"{$toAddress}\" is not one Gmail can send to");
        }

        $to = $this->mailbox($toAddress, $toName);

        // Without a name of our own on the From line the customer's mail client shows whatever
        // the Google account happens to be called, which is the mailbox owner, not the shop.
        $senderName = Arr::get($session->shop->settings, 'gmail.sender_name') ?: $session->shop->name;
        $from       = $senderName ? $this->encodeHeader($senderName)." <{$mailboxAddress}>" : $mailboxAddress;

        $headers = [
            "From: {$from}",
            "To: {$to}",
            'Subject: '.$this->encodeHeader($subject),
            "Message-ID: {$messageId}",
        ];

        $copies = array_map(
            fn (array $person) => $this->mailbox($person['address'], $person['name']),
            array_filter(Arr::get($chatMessage->metadata ?? [], 'email_cc', []), fn (array $person) => self::headerAddress($person['address']))
        );

        if ($copies) {
            $headers[] = 'Cc: '.implode(', ', $copies);
        }

        if ($replyToHeader) {
            $headers[] = "In-Reply-To: {$replyToHeader}";
            $headers[] = 'References: '.implode(' ', self::references($metadata));
        }

        // RFC 3834: says it was sent by itself, so the other side's auto-responder does not answer it.
        if (Arr::get($chatMessage->metadata ?? [], 'auto_submitted')) {
            $headers[] = 'Auto-Submitted: auto-replied';
            $headers[] = 'X-Auto-Response-Suppress: All';
        }

        $headers[] = 'MIME-Version: 1.0';

        $signature = '';

        if ($chatMessage->sender_type === ChatSenderTypeEnum::AGENT && $chatMessage->sender_id) {
            $agent     = ChatAgent::find($chatMessage->sender_id);
            $signature = $agent?->signature ?: '';
        }

        // ponytail: inline pictures sit outside the 18MB budget; pasted screenshots are small, fold them in if one ever is not
        [$inlinePictures, $files] = $chatMessage->attachedFiles()->partition(fn ($file) => filled($file->getCustomProperty('content_id')) && str_starts_with((string) $file->mime_type, 'image/'))->map->values()->all();

        [$attachments, $linkedFiles] = self::splitByGmailLimit($files, $inlinePictures->sum('size'));

        $messageText = $chatMessage->message_text ?? '';

        foreach ($linkedFiles as $linkedFile) {
            $messageText .= "\n\n📎 ".($linkedFile->name ?: $linkedFile->file_name).' ('.round($linkedFile->size / 1024 / 1024, 1).'MB): '
                .route('grp.api.chats.chat.attachment.download', ['ulid' => $linkedFile->ulid, 'inline' => 1]);
        }

        [$bodyPart, $bodyHtml] = $this->bodyPart(trim($messageText), $signature, $inlinePictures->map(fn ($picture) => $picture->getCustomProperty('content_id'))->all());

        if ($inlinePictures->isNotEmpty()) {
            $bodyPart = $this->withInlinePictures($bodyPart, $inlinePictures);
        }

        if ($attachments->isEmpty()) {
            return [implode("\r\n", [...$headers, ...$bodyPart]), $bodyHtml];
        }

        $boundary = 'aiku-'.bin2hex(random_bytes(12));

        $lines = [
            ...$headers,
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            ...$bodyPart,
        ];

        foreach ($attachments as $attachment) {
            $fileName = $this->encodeHeader(str_replace(['"', "\r", "\n"], '', $attachment->name ?: $attachment->file_name));

            array_push(
                $lines,
                "--{$boundary}",
                "Content-Type: {$attachment->mime_type}; name=\"{$fileName}\"",
                "Content-Disposition: attachment; filename=\"{$fileName}\"",
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode(GetChatMediaContents::run($attachment))),
            );
        }

        $lines[] = "--{$boundary}--";

        return [implode("\r\n", $lines), $bodyHtml];
    }

    /**
     * Gmail refuses a mail over 25MB once base64 has grown it by a third, so files are attached
     * in order while they fit in 18MB and the rest go as download links in the body.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    public static function splitByGmailLimit(\Illuminate\Support\Collection $files, int $attachedBytes = 0): array
    {

        return $files->partition(function ($file) use (&$attachedBytes) {
            if ($attachedBytes + $file->size > self::MAX_ATTACHED_BYTES) {
                return false;
            }
            $attachedBytes += $file->size;

            return true;
        })->map->values()->all();
    }

    /**
     * A mail goes as HTML beside its readable text whenever there is something to show: the
     * formatting the agent picked, or a signature holding a logo, since HTML in a text/plain
     * mail is read as its own source. A mail with neither stays plain as it always was.
     *
     * @param  array<int, string>  $pictureIds  the Content-ID of each picture pasted into the text
     * @return array{0: array<int, string>, 1: string}  the MIME lines for the body, header first, and its html
     */
    private function bodyPart(string $messageText, string $signature, array $pictureIds = []): array
    {
        $isHtmlSignature = $signature !== '' && $signature !== strip_tags($signature);
        $messageHtml     = self::placePictures(self::markupToHtml($messageText), $pictureIds);
        $messageText     = trim(preg_replace_callback("/\n?".self::PICTURE_TOKEN."\n?/u", fn (array $match) => self::isPicture($match, $pictureIds) ? "\n" : $match[0], $messageText) ?? $messageText);

        if (! $pictureIds && ! $isHtmlSignature && $messageHtml === nl2br(e($messageText))) {
            $body = trim($messageText."\n\n".$signature);

            return [$this->textPart($body), nl2br(e($body))];
        }

        $signatureHtml = $isHtmlSignature ? $signature : nl2br(e($signature));

        $textPart = $this->textPart(trim($messageText."\n\n".($isHtmlSignature ? $this->htmlToText($signature) : $signature)));
        $html     = $signature === '' ? $messageHtml : $messageHtml.'<br><br>'.$signatureHtml;
        $htmlPart = [
            'Content-Type: text/html; charset=utf-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
        ];

        $boundary = 'aiku-alt-'.bin2hex(random_bytes(12));

        return [[
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            ...$textPart,
            "--{$boundary}",
            ...$htmlPart,
            "--{$boundary}--",
        ], $html];
    }

    public const string PICTURE_TOKEN = '\[[^\[\]\n:]{1,24}:\s*([A-Za-z0-9-]{1,64})\s*\]';

    /**
     * A translator may return [imagen: id] or [Bild:id]: any such bracket naming one of the
     * pictures is that picture, and only the exact [image:...] form is dropped when it names none,
     * so a "[Note: A12]" the agent wrote is left alone.
     *
     * @param  array<int, string>  $match
     * @param  array<int, string>  $pictureIds
     */
    private static function isPicture(array $match, array $pictureIds): bool
    {
        return in_array($match[1], $pictureIds, true) || preg_match('/^\n?\[image:[A-Za-z0-9-]{1,64}\]/', $match[0]);
    }

    /**
     * The composer writes a pasted picture as [image:<content id>] where it was pasted. A picture
     * whose token the translator lost still goes, at the end, and a token naming no file goes.
     *
     * @param  array<int, string>  $pictureIds
     */
    public static function placePictures(string $html, array $pictureIds): string
    {
        $placed = [];

        $html = preg_replace_callback('/'.self::PICTURE_TOKEN.'(<br \/>)?/u', function (array $match) use ($pictureIds, &$placed): string {
            if (! in_array($match[1], $pictureIds, true)) {
                return self::isPicture($match, $pictureIds) ? '' : $match[0];
            }

            $placed[] = $match[1];

            return self::pictureTag($match[1]);
        }, $html) ?? $html;

        foreach (array_diff($pictureIds, $placed) as $pictureId) {
            $html .= '<br>'.self::pictureTag($pictureId);
        }

        return $html;
    }

    private static function pictureTag(string $pictureId): string
    {
        return '<div><img src="cid:'.e($pictureId).'@aiku.io" alt="" style="max-width:100%;height:auto"></div>';
    }

    /**
     * @param  array<int, string>  $bodyPart
     * @return array<int, string>
     */
    private function withInlinePictures(array $bodyPart, \Illuminate\Support\Collection $pictures): array
    {
        $boundary = 'aiku-rel-'.bin2hex(random_bytes(12));

        $lines = [
            "Content-Type: multipart/related; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            ...$bodyPart,
        ];

        foreach ($pictures as $picture) {
            $fileName = $this->encodeHeader(str_replace(['"', "\r", "\n"], '', $picture->name ?: $picture->file_name));

            array_push(
                $lines,
                "--{$boundary}",
                "Content-Type: {$picture->mime_type}; name=\"{$fileName}\"",
                "Content-Disposition: inline; filename=\"{$fileName}\"",
                'Content-ID: <'.$picture->getCustomProperty('content_id').'@aiku.io>',
                'Content-Transfer-Encoding: base64',
                '',
                chunk_split(base64_encode(GetChatMediaContents::run($picture))),
            );
        }

        $lines[] = "--{$boundary}--";

        return $lines;
    }

    /**
     * The same markers the chat bubble renders (formatWhatsappMarkup in useWhatsappMarkup.ts),
     * so the customer's mail reads as the agent saw it. The text is escaped before any tag is
     * put back, and a marker only counts where it touches a word, so snake_case and 2*3*4
     * are left alone. Links are lifted out first, so a marker inside a URL never opens a tag,
     * and go back as anchors with trailing punctuation left outside them.
     */
    public static function markupToHtml(string $text): string
    {
        $links = [];
        $text  = str_replace("\0", "", $text);
        $text  = preg_replace_callback('/\b(?:https?:\/\/|www\.)[^\s<>"]+/iu', function (array $match) use (&$links): string {
            $url = preg_replace('/[.,;:!?\'")\]*_~]+$/u', '', $match[0]) ?? $match[0];
            while (strlen($match[0]) > strlen($url) && $match[0][strlen($url)] === ')' && substr_count($url, '(') > substr_count($url, ')')) {
                $url .= ')';
            }
            $links[] = $url;

            return "\0".(count($links) - 1)."\0".substr($match[0], strlen($url));
        }, $text) ?? $text;

        $html = preg_replace('/```([\s\S]+?)```/u', '<code style="font-family:monospace">$1</code>', e($text)) ?? e($text);

        foreach (['__' => 'u', '*' => 'strong', '_' => 'em', '~' => 's'] as $marker => $tag) {
            $m    = preg_quote($marker, '/');
            $html = preg_replace(
                "/(^|[^\\w{$m}]){$m}([^\\s{$m}][^{$m}\\n]*[^\\s{$m}]|[^\\s{$m}]){$m}(?![\\w{$m}])/u",
                "\$1<{$tag}>\$2</{$tag}>",
                $html
            ) ?? $html;
        }

        $html = preg_replace_callback('/\0(\d+)\0/', function (array $match) use ($links): string {
            $url  = $links[(int) $match[1]];
            $href = preg_match('/^www\./i', $url) ? 'https://'.$url : $url;

            return '<a href="'.e($href).'" target="_blank" rel="noopener noreferrer">'.e($url).'</a>';
        }, $html) ?? $html;

        return nl2br($html);
    }

    /**
     * @return array<int, string>
     */
    private function textPart(string $body): array
    {
        return [
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($body)),
        ];
    }

    private function htmlToText(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/div|\/tr|\/h[1-6])[^>]*>/i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /**
     * A name holding a comma, as "Doe, Jane" does, reads as two recipients unless it is quoted.
     */
    public static function mailbox(string $address, ?string $name): string
    {
        $address = self::headerAddress($address) ?? $address;

        if (! $name) {
            return $address;
        }

        $name = match (true) {
            (bool) preg_match('/[^\x20-\x7E]/', $name)    => mb_encode_mimeheader($name, 'UTF-8'),
            (bool) preg_match('/[()<>@,;:\\\\".\[\]]/', $name) => '"'.addcslashes($name, '"\\').'"',
            default                                         => $name,
        };

        return "{$name} <{$address}>";
    }

    /**
     * Gmail refuses a header naming an accented domain as written (info@mémorablemoments.be),
     * so the domain goes in its punycode form. Null when the address still is not one, as an
     * accented name before the @ is, which no header can carry.
     */
    public static function headerAddress(?string $address): ?string
    {
        $address = trim((string) $address);
        $domain  = Str::afterLast($address, '@');

        if (str_contains($address, '@') && preg_match('/[^\x20-\x7E]/', $domain)) {
            $domain  = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $domain;
            $address = Str::beforeLast($address, '@').'@'.$domain;
        }

        return filter_var($address, FILTER_VALIDATE_EMAIL) ? $address : null;
    }

    public static function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? mb_encode_mimeheader($value, 'UTF-8') : $value;
    }
}
