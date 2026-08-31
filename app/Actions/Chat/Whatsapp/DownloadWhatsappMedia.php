<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChatMessage;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\Mime\MimeTypes;

class DownloadWhatsappMedia
{
    use AsAction;
    use WithWhatsappCredentials;

    public string $jobQueue = 'urgent';

    /**
     * WhatsApp media types that end up in the `chat_images` collection, the rest
     * (document, audio, video) are stored as plain attachments.
     */
    public const IMAGE_TYPES = ['image', 'sticker'];

    public const MEDIA_TYPES = ['image', 'sticker', 'document', 'audio', 'video'];

    public function asJob(MetaChatMessage $metaChatMessage, Shop $shop): void
    {
        $this->handle($metaChatMessage, $shop);
    }

    /**
     * Pulls the binary behind an inbound media message: Meta only hands out the
     * media id in the webhook, the download URL has to be resolved separately and
     * expires after five minutes.
     */
    public function handle(MetaChatMessage $metaChatMessage, Shop $shop): ?Media
    {
        if ($metaChatMessage->media_id) {
            return null;
        }

        $mediaId = (string) Arr::get($metaChatMessage->metadata, 'wa_payload.id');

        ['access_token' => $accessToken] = $this->whatsappCredentials($shop);

        if ($mediaId === '' || $accessToken === '') {
            Log::warning('WhatsApp media cannot be downloaded', [
                'meta_chat_message_id' => $metaChatMessage->id,
                'media_id'             => $mediaId,
                'shop'                 => $shop->slug,
            ]);

            return null;
        }

        $mediaUrl = $this->mediaUrlFromWebhook($metaChatMessage);
        $download = $mediaUrl ? Http::withToken($accessToken)->get($mediaUrl['url']) : null;

        // The webhook URL carries its own short expiry, so a stale one falls back to
        // asking Meta for a fresh link before giving up.
        if (!$download || $download->failed()) {
            $mediaUrl = $this->resolveMediaUrl($metaChatMessage, $mediaId, $accessToken);

            if (!$mediaUrl) {
                return null;
            }

            $download = Http::withToken($accessToken)->get($mediaUrl['url']);
        }

        if ($download->failed()) {
            Log::warning('WhatsApp media download failed', [
                'meta_chat_message_id' => $metaChatMessage->id,
                'media_id'             => $mediaId,
                'status'               => $download->status(),
            ]);

            return null;
        }

        $isImage  = in_array(Arr::get($metaChatMessage->metadata, 'wa_type'), self::IMAGE_TYPES, true);
        $fileName = $this->resolveFileName($metaChatMessage, $mediaId, $mediaUrl['mime_type']);
        $path     = tempnam(sys_get_temp_dir(), 'wa-media-');

        file_put_contents($path, $download->body());

        try {
            $media = StoreMediaFromFile::run(
                $metaChatMessage,
                [
                    'path'         => $path,
                    'originalName' => $fileName,
                    'extension'    => pathinfo($fileName, PATHINFO_EXTENSION),
                    'checksum'     => md5_file($path),
                ],
                $isImage ? 'chat_images' : 'chat_attachments',
                $isImage ? 'image' : 'file'
            );
        } finally {
            unlink($path);
        }

        $metaChatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => $isImage ? ChatMessageTypeEnum::IMAGE : ChatMessageTypeEnum::FILE,
        ]);

        // The bubble was already broadcast while the file was still downloading, so it is
        // sent again now that there is something to show in it.
        $metaChatMessage = $metaChatMessage->fresh(['attachment', 'metaChatSession']);

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage);
        BroadcastMetaChatListEvent::dispatch($metaChatMessage, $metaChatMessage->metaChatSession);

        return $media;
    }

    /**
     * Recent API versions ship the download URL inside the message node itself,
     * which saves the extra lookup round-trip.
     *
     * @return array{url: string, mime_type: string}|null
     */
    protected function mediaUrlFromWebhook(MetaChatMessage $metaChatMessage): ?array
    {
        $url = (string) Arr::get($metaChatMessage->metadata, 'wa_payload.url');

        if ($url === '') {
            return null;
        }

        return [
            'url'       => $url,
            'mime_type' => (string) Arr::get($metaChatMessage->metadata, 'wa_payload.mime_type'),
        ];
    }

    /**
     * @return array{url: string, mime_type: string}|null
     */
    protected function resolveMediaUrl(MetaChatMessage $metaChatMessage, string $mediaId, string $accessToken): ?array
    {
        $response = Http::withToken($accessToken)->get($this->whatsappEndpoint($mediaId));

        if ($response->failed() || blank($response->json('url'))) {
            Log::warning('WhatsApp media url could not be resolved', [
                'meta_chat_message_id' => $metaChatMessage->id,
                'media_id'             => $mediaId,
                'status'               => $response->status(),
                'body'                 => $response->json(),
            ]);

            return null;
        }

        return [
            'url'       => (string) $response->json('url'),
            'mime_type' => (string) ($response->json('mime_type') ?: Arr::get($metaChatMessage->metadata, 'wa_payload.mime_type')),
        ];
    }

    /**
     * Documents carry their own filename, everything else is named after the media
     * id with an extension derived from the mime type Meta reports.
     */
    protected function resolveFileName(MetaChatMessage $metaChatMessage, string $mediaId, string $mimeType): string
    {
        $fileName = trim((string) Arr::get($metaChatMessage->metadata, 'wa_payload.filename'));

        if ($fileName !== '' && pathinfo($fileName, PATHINFO_EXTENSION) !== '') {
            return $fileName;
        }

        $mimeType  = trim(explode(';', $mimeType)[0]);
        $extension = Arr::first(MimeTypes::getDefault()->getExtensions($mimeType)) ?? 'bin';

        return ($fileName !== '' ? $fileName : 'whatsapp-'.$mediaId).'.'.$extension;
    }
}
