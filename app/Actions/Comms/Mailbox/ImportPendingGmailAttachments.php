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
 * Pictures are the message in a lot of mail: a photograph of a damaged item, a scanned form, the
 * artwork in a supplier's circular. With the markup discarded they are the only thing left to see,
 * so an email whose images are missing reads as broken.
 *
 * What is skipped is the furniture: signature logos, social icons, spacers and tracking pixels,
 * all of which are small. A stranger's larger files still wait in Gmail until an agent replies,
 * which is what shows the sender is real.
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

            // The pictures came in when the mail did. Downloading everything again would attach
            // them a second time, so what is already here is left alone.
            $already = $message->attachedFiles()->pluck('name')->all();

            $files = $this->download(
                $client,
                $gmailMessageId,
                $client->getMessage($gmailMessageId),
                skip: $already
            );

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
     * Below this an inline image is decoration rather than content. Tracking pixels are a few
     * hundred bytes, icons a couple of kilobytes; a photograph anybody meant to send is larger.
     */
    private const int INLINE_IMAGE_MIN_BYTES = 8192;

    /** A stranger does not get to hand us anything bigger than this before an agent has replied. */
    private const int GUEST_MAX_BYTES = 5242880;

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     * @return array<int, UploadedFile>
     */
    public function download(GmailClient $client, string $gmailMessageId, array $raw, bool $trusted = true, array $skip = []): array
    {
        $files = [];

        foreach ($this->candidates($client, $raw) as $attachment) {
            if (! $this->isWorthImporting($attachment, $trusted)) {
                continue;
            }

            if (in_array(basename((string) $attachment['filename']), $skip, true)) {
                continue;
            }

            $content = match (true) {
                (bool) $attachment['driveFileId'] => $client->driveFileContents($attachment['driveFileId']),
                (bool) $attachment['attachmentId'] => $client->getAttachment($gmailMessageId, $attachment['attachmentId']),
                default => GmailMessageParser::decodeData((string) $attachment['data']),
            };

            $path = tempnam(sys_get_temp_dir(), 'gmail-attachment-');
            file_put_contents($path, $content);

            $files[] = new UploadedFile($path, basename($attachment['filename']), $attachment['mimeType'], null, true);
        }

        return $files;
    }

    /**
     * Everything the message offers, wherever it is kept. A photograph over Gmail's attachment
     * limit is not in the mail at all: it is a Drive link, and Drive is asked what it is before
     * any of the rules below can judge it. A file the sender never shared with us answers
     * nothing, and is left as the link the customer sent.
     *
     * @return array<int, array{filename: string, mimeType: string, attachmentId: ?string, driveFileId: ?string, data: ?string, inline: bool, size: int}>
     */
    private function candidates(GmailClient $client, array $raw): array
    {
        $candidates = array_map(
            fn (array $attachment) => $attachment + ['driveFileId' => null],
            GmailMessageParser::attachments(Arr::get($raw, 'payload', []))
        );

        foreach (GmailMessageParser::driveFileIds(GmailMessageParser::htmlBody($raw)) as $fileId) {
            $file = $client->driveFile($fileId);

            if (! $file) {
                continue;
            }

            $candidates[] = [
                'filename'     => $file['name'],
                'mimeType'     => $file['mimeType'],
                'attachmentId' => null,
                'driveFileId'  => $fileId,
                'data'         => null,
                'inline'       => false,
                'size'         => $file['size'],
            ];
        }

        return $candidates;
    }

    /**
     * How many files are waiting for an agent to reply. Only what a reply would actually import
     * counts: a signature logo is never coming, so counting it would promise a file that does
     * not exist.
     *
     * @param  array<string, mixed>  $raw
     */
    public function countDeferred(GmailClient $client, array $raw, bool $trusted): int
    {
        if ($trusted) {
            return 0;
        }

        return collect($this->candidates($client, $raw))
            ->filter(fn (array $attachment) => $this->isWorthImporting($attachment, true)
                && ! $this->isWorthImporting($attachment, false))
            ->count();
    }

    /**
     * @param  array<string, mixed>  $attachment
     */
    private function isWorthImporting(array $attachment, bool $trusted): bool
    {
        $size    = (int) ($attachment['size'] ?? 0);
        $isImage = str_starts_with((string) $attachment['mimeType'], 'image/');

        if (($attachment['inline'] ?? false) && $size < self::INLINE_IMAGE_MIN_BYTES) {
            return false;
        }

        if ($trusted) {
            return true;
        }

        // Until an agent has replied, a stranger's mail gives up its pictures and nothing else.
        return $isImage && $size <= self::GUEST_MAX_BYTES;
    }
}
