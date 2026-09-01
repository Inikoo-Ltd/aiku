<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Chat\Whatsapp\Concerns;

use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Helpers\Media;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

/**
 * Builds the `type: template` payload Meta expects, shared by the one-off chat send and
 * the campaign send. Both post the same body to different paths, `/messages` for a chat
 * reply and `/marketing_messages` for a campaign, so only the URL differs.
 *
 * Scoping is by shop id rather than by chat session: a campaign has a shop but no single
 * session to take one from.
 */
trait WithWhatsappTemplatePayload
{
    use WithWhatsappCredentials;

    /**
     * Meta matches the components sent against the ones the template was approved with,
     * and answers `(#132012) Parameter format does not match format in the created
     * template` when they differ. A template with a media header therefore has to carry a
     * header component on every send, not just a body.
     *
     * @return array{ok: bool, payload?: array<string, mixed>, header_media?: Media|null, message?: string, code?: int}
     */
    protected function templatePayload(
        ?int $shopId,
        string $to,
        string $name,
        string $language,
        array $parameters,
        string $phoneNumberId,
        string $accessToken
    ): array {
        $record = $this->findTemplate($shopId, $name, $language);

        // Meta pauses or rejects templates on its own, and only an approved one can be
        // sent. Catching it here explains why instead of leaving Meta's error code to.
        if ($record && $record->status !== 'APPROVED') {
            return [
                'ok'      => false,
                'message' => __(':name is :status on WhatsApp and cannot be sent.', [
                    'name'   => $name,
                    'status' => strtolower((string) $record->status),
                ]),
                'code'    => 422,
            ];
        }

        $components  = [];
        $headerMedia = null;
        $header      = collect(Arr::get($record?->data ?? [], 'components', []))->firstWhere('type', 'HEADER');
        $format      = Arr::get($header ?? [], 'format', 'TEXT');

        if ($header && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $headerComponent = $this->mediaHeaderComponent($record, $format, $phoneNumberId, $accessToken);

            if (!$headerComponent['ok']) {
                return $headerComponent;
            }

            $components[] = $headerComponent['component'];
            $headerMedia  = $headerComponent['media'];
        }

        if ($parameters) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters),
            ];
        }

        $template = [
            'name'     => $name,
            'language' => ['code' => $language],
        ];

        if ($components) {
            $template['components'] = $components;
        }

        return [
            'ok'           => true,
            'header_media' => $headerMedia,
            'payload'      => [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'template',
                'template'          => $template,
            ],
        ];
    }

    protected function findTemplate(?int $shopId, string $name, string $language): ?MetaMessageTemplate
    {
        return MetaMessageTemplate::where('name', $name)
            ->where('language', $language)
            ->whereNotNull('template_id')
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))
            ->orderByRaw("CASE WHEN status = 'APPROVED' THEN 0 ELSE 1 END")
            ->first();
    }

    protected function headerMessageType(Media $media): ChatMessageTypeEnum
    {
        return str_starts_with((string) $media->mime_type, 'image/')
            ? ChatMessageTypeEnum::IMAGE
            : ChatMessageTypeEnum::FILE;
    }

    /**
     * The review sample Meta holds cannot be reused for sending, so the file kept in Aiku
     * is uploaded again and referenced by its fresh media id.
     *
     * @return array{ok: bool, component?: array<string, mixed>, media?: Media, message?: string, code?: int}
     */
    protected function mediaHeaderComponent(?MetaMessageTemplate $record, string $format, string $phoneNumberId, string $accessToken): array
    {
        $media = $record?->headerMedia;

        if (!$media) {
            return [
                'ok'      => false,
                'message' => __('This template shows an image above the message, but no file is set for it. Add one on the template page first.'),
                'code'    => 422,
            ];
        }

        $upload = $this->uploadMediaFromPath($phoneNumberId, $accessToken, $media->getPath(), $media->mime_type, $media->file_name);

        if (!$upload['ok']) {
            return $upload;
        }

        $key = strtolower($format);

        return [
            'ok'        => true,
            'media'     => $media,
            'component' => [
                'type'       => 'header',
                'parameters' => [[
                    'type' => $key,
                    $key   => array_filter([
                        'id'       => $upload['media_id'],
                        'filename' => $format === 'DOCUMENT' ? $media->file_name : null,
                    ]),
                ]],
            ],
        ];
    }

    protected function renderTemplateBody(?int $shopId, string $name, string $language, array $parameters): string
    {
        $template = $this->findTemplate($shopId, $name, $language);

        $body = Arr::get(
            collect(Arr::get($template?->data, 'components', []))->firstWhere('type', 'BODY') ?? [],
            'text',
            $name
        );

        foreach ($parameters as $index => $parameter) {
            $body = str_replace('{{'.($index + 1).'}}', $parameter, $body);
        }

        return $body;
    }

    /**
     * @return array{ok: bool, media_id?: string, message?: string, code?: int}
     */
    protected function uploadMediaFromPath(string $phoneNumberId, string $accessToken, string $path, ?string $mimeType, string $fileName): array
    {
        if (!is_readable($path)) {
            return [
                'ok'      => false,
                'message' => __('The file could not be read from storage.'),
                'code'    => 422,
            ];
        }

        $response = Http::withToken($accessToken)
            ->attach('file', file_get_contents($path), $fileName, ['Content-Type' => $mimeType])
            ->post(
                $this->whatsappEndpoint($phoneNumberId.'/media'),
                ['messaging_product' => 'whatsapp']
            );

        if ($response->failed() || !$response->json('id')) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Failed to upload media to WhatsApp.'),
                'code'    => 422,
            ];
        }

        return ['ok' => true, 'media_id' => $response->json('id')];
    }
}
