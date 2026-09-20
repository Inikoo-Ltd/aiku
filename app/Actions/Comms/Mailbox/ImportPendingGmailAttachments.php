<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 19 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\Chat\ChatSession\SendChatMessage;
use App\Models\Chat\ChatSession;
use App\Services\Gmail\GmailClient;
use App\Services\Gmail\GmailMessageParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Guest mail is mostly junk, so its attachments stay in Gmail until an agent replies to the
 * thread; a reply is what shows the sender is real. Customer mail imports its files straight away.
 */
class ImportPendingGmailAttachments
{
    use AsAction;

    public function handle(ChatSession $chatSession): int
    {
        $messages = $chatSession->messages()->whereNotNull('metadata->gmail_pending_attachments')->get();

        if ($messages->isEmpty() || ! $client = GmailClient::forShop($chatSession->shop)) {
            return 0;
        }

        $imported = 0;

        foreach ($messages as $message) {
            $gmailMessageId = Arr::get($message->metadata, 'gmail_message_id');
            $files          = $this->download($client, $gmailMessageId, $client->getMessage($gmailMessageId));

            if ($files) {
                SendChatMessage::make()->processMessageAttachments($message, $files);
            }

            foreach ($files as $file) {
                @unlink($file->getPathname());
            }

            $message->update(['metadata' => Arr::except($message->metadata, 'gmail_pending_attachments')]);
            $imported += count($files);
        }

        return $imported;
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function download(GmailClient $client, string $gmailMessageId, array $raw): array
    {
        $files = [];

        foreach (GmailMessageParser::attachments(Arr::get($raw, 'payload', [])) as $attachment) {
            $content = $attachment['attachmentId']
                ? $client->getAttachment($gmailMessageId, $attachment['attachmentId'])
                : GmailMessageParser::decodeData((string) $attachment['data']);

            $path = tempnam(sys_get_temp_dir(), 'gmail-attachment-');
            file_put_contents($path, $content);

            $files[] = new UploadedFile($path, basename($attachment['filename']), $attachment['mimeType'], null, true);
        }

        return $files;
    }
}
