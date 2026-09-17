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
use App\Models\Chat\ChatMessage;
use App\Services\Gmail\GmailClient;
use Illuminate\Support\Arr;
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

        if (! $threadId) {
            return;
        }

        $client = GmailClient::forShop($session->shop);

        if (! $client) {
            return;
        }

        $raw = $this->buildRawMessage($session, $chatMessage);

        $result = $client->send($raw, $threadId);

        $chatMessage->update([
            'metadata' => array_merge($chatMessage->metadata ?? [], [
                'gmail_message_id' => Arr::get($result, 'id'),
            ]),
        ]);
    }

    private function buildRawMessage($session, ChatMessage $chatMessage): string
    {
        $metadata = $session->metadata ?? [];

        $mailboxAddress = Arr::get($session->shop->settings, 'gmail.email');
        $toAddress      = Arr::get($metadata, 'email_from');
        $toName         = Arr::get($metadata, 'email_from_name');
        $subject        = Arr::get($metadata, 'email_subject') ?? '';
        $replyToHeader  = Arr::get($metadata, 'gmail_last_header_message_id');

        if (! str_starts_with(trim($subject), 'Re:')) {
            $subject = 'Re: '.$subject;
        }

        $to = $toName ? $this->encodeHeader($toName)." <{$toAddress}>" : $toAddress;

        $headers = [
            "From: {$mailboxAddress}",
            "To: {$to}",
            'Subject: '.$this->encodeHeader($subject),
        ];

        if ($replyToHeader) {
            $headers[] = "In-Reply-To: {$replyToHeader}";
            $headers[] = "References: {$replyToHeader}";
        }

        $headers[] = 'MIME-Version: 1.0';

        $messageBody = $chatMessage->message_text ?? '';

        if ($chatMessage->sender_type === ChatSenderTypeEnum::AGENT && $chatMessage->sender_id) {
            $agent = ChatAgent::find($chatMessage->sender_id);
            if ($agent && $agent->signature) {
                $messageBody .= "\n\n".$agent->signature;
            }
        }

        $textPart = [
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($messageBody)),
        ];

        $attachment = $chatMessage->attachment;

        if (! $attachment) {
            return implode("\r\n", [...$headers, ...$textPart]);
        }

        $boundary = 'aiku-'.bin2hex(random_bytes(12));
        $fileName = $this->encodeHeader(str_replace(['"', "\r", "\n"], '', $attachment->name ?: $attachment->file_name));

        return implode("\r\n", [
            ...$headers,
            "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            ...$textPart,
            "--{$boundary}",
            "Content-Type: {$attachment->mime_type}; name=\"{$fileName}\"",
            "Content-Disposition: attachment; filename=\"{$fileName}\"",
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode(stream_get_contents($attachment->stream()))),
            "--{$boundary}--",
        ]);
    }

    private function encodeHeader(string $value): string
    {
        return preg_match('/[^\x20-\x7E]/', $value) ? mb_encode_mimeheader($value, 'UTF-8') : $value;
    }
}
