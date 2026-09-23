<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Enums\CRM\Livechat\ChatChannelEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Actions\Chat\ChatSession\GetChatMediaContents;
use App\Models\Chat\ChatMessage;
use App\Services\Gmail\GmailClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class SendChatMessageByGmail
{
    use AsAction;

    public function handle(ChatMessage $chatMessage): void
    {
        $session = $chatMessage->chatSession;

        if (! $session || $session->channel !== ChatChannelEnum::EMAIL) {
            return;
        }

        $threadId = Arr::get($session->metadata, 'gmail_thread_id');

        $client = GmailClient::forShop($session->shop);

        if (! $client) {
            return;
        }

        $messageId = $this->newHeaderMessageId($session);
        $raw       = $this->buildRawMessage($session, $chatMessage, $messageId);

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

        $chatMessage->update([
            'metadata' => array_merge($chatMessage->metadata ?? [], [
                'gmail_message_id' => Arr::get($result, 'id'),
            ]),
        ]);
    }

    public static function references(array $metadata, ?string ...$append): array
    {
        return array_values(array_unique(array_filter([
            ...Arr::get($metadata, 'gmail_references', []),
            Arr::get($metadata, 'gmail_last_header_message_id'),
            ...$append,
        ])));
    }

    private function newHeaderMessageId($session): string
    {
        $domain = Str::after((string) Arr::get($session->shop->settings, 'gmail.email'), '@') ?: 'aiku.io';

        return '<'.Str::ulid().'@'.$domain.'>';
    }

    private function buildRawMessage($session, ChatMessage $chatMessage, string $messageId): string
    {
        $metadata = $session->metadata ?? [];

        $mailboxAddress = Arr::get($session->shop->settings, 'gmail.email');
        $toAddress      = Arr::get($metadata, 'email_from');
        $toName         = Arr::get($metadata, 'email_from_name');
        $subject        = Arr::get($metadata, 'email_subject') ?? '';
        $replyToHeader  = Arr::get($metadata, 'gmail_last_header_message_id');

        // Only an answer is prefixed: the first mail of a conversation we started replies to
        // nothing, and a subject reading "Re:" out of the blue looks like a lost thread.
        if ($replyToHeader && ! str_starts_with(trim($subject), 'Re:')) {
            $subject = 'Re: '.$subject;
        }

        $to = $toName ? $this->encodeHeader($toName)." <{$toAddress}>" : $toAddress;

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

        if ($replyToHeader) {
            $headers[] = "In-Reply-To: {$replyToHeader}";
            $headers[] = 'References: '.implode(' ', self::references($metadata));
        }

        $headers[] = 'MIME-Version: 1.0';

        $signature = '';

        if ($chatMessage->sender_type === ChatSenderTypeEnum::AGENT && $chatMessage->sender_id) {
            $agent     = ChatAgent::find($chatMessage->sender_id);
            $signature = $agent?->signature ?: '';
        }

        $bodyPart = $this->bodyPart($chatMessage->message_text ?? '', $signature);

        $attachments = $chatMessage->attachedFiles();

        if ($attachments->isEmpty()) {
            return implode("\r\n", [...$headers, ...$bodyPart]);
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

        return implode("\r\n", $lines);
    }

    /**
     * A mail goes as HTML beside its readable text whenever there is something to show: the
     * formatting the agent picked, or a signature holding a logo, since HTML in a text/plain
     * mail is read as its own source. A mail with neither stays plain as it always was.
     *
     * @return array<int, string>  the MIME lines for the body, header first
     */
    private function bodyPart(string $messageText, string $signature): array
    {
        $isHtmlSignature = $signature !== '' && $signature !== strip_tags($signature);
        $messageHtml     = self::markupToHtml($messageText);

        if (! $isHtmlSignature && $messageHtml === nl2br(e($messageText))) {
            return $this->textPart(trim($messageText."\n\n".$signature));
        }

        $signatureHtml = $isHtmlSignature ? $signature : nl2br(e($signature));

        $textPart = $this->textPart(trim($messageText."\n\n".($isHtmlSignature ? $this->htmlToText($signature) : $signature)));
        $htmlPart = [
            'Content-Type: text/html; charset=utf-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($signature === '' ? $messageHtml : $messageHtml.'<br><br>'.$signatureHtml)),
        ];

        $boundary = 'aiku-alt-'.bin2hex(random_bytes(12));

        return [
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            ...$textPart,
            "--{$boundary}",
            ...$htmlPart,
            "--{$boundary}--",
        ];
    }

    /**
     * The same markers the chat bubble renders (formatWhatsappMarkup in useWhatsappMarkup.ts),
     * so the customer's mail reads as the agent saw it. The text is escaped before any tag is
     * put back, and a marker only counts where it touches a word, so snake_case and 2*3*4
     * are left alone.
     */
    public static function markupToHtml(string $text): string
    {
        $html = preg_replace('/```([\s\S]+?)```/u', '<code style="font-family:monospace">$1</code>', e($text)) ?? e($text);

        foreach (['__' => 'u', '*' => 'strong', '_' => 'em', '~' => 's'] as $marker => $tag) {
            $m    = preg_quote($marker, '/');
            $html = preg_replace(
                "/(^|[^\\w{$m}]){$m}([^\\s{$m}][^{$m}\\n]*[^\\s{$m}]|[^\\s{$m}]){$m}(?![\\w{$m}])/u",
                "\$1<{$tag}>\$2</{$tag}>",
                $html
            ) ?? $html;
        }

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

    private function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? mb_encode_mimeheader($value, 'UTF-8') : $value;
    }
}
