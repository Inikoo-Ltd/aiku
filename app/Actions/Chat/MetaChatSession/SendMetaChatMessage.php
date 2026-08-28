<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed Aug 19 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\WhatsappMediaTypeEnum;
use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Helpers\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendMetaChatMessage
{
    use AsAction;
    use WithWhatsappCredentials;

    public function rules(): array
    {
        return [
            'message_text'          => [
                'required_without_all:image,file,template_name',
                'nullable',
                'string',
                'max:4096'
            ],
            'image'                 => [
                'sometimes',
                'nullable',
                File::types(WhatsappMediaTypeEnum::IMAGE->extensions())
                    ->max(WhatsappMediaTypeEnum::IMAGE->maxKilobytes())
            ],
            'file'                  => [
                'sometimes',
                'nullable',
                File::types(WhatsappMediaTypeEnum::DOCUMENT->extensions())
                    ->max(WhatsappMediaTypeEnum::DOCUMENT->maxKilobytes())
            ],
            'template_name'         => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('meta_message_templates', 'name')
            ],
            'template_language'     => ['required_with:template_name', 'string'],
            'template_parameters'   => ['sometimes', 'array'],
            'template_parameters.*' => ['string', 'max:1024'],
            'replied_to_id'         => ['sometimes', 'nullable', 'integer'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent, array $modelData): array
    {
        $shop = $metaChatSession->shop;

        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return [
                'ok'      => false,
                'message' => __('WhatsApp is not configured for this shop.'),
                'code'    => 422,
            ];
        }

        $to           = preg_replace('/\D/', '', (string) $metaChatSession->phone_number);
        $messageText  = trim(strip_tags($modelData['message_text'] ?? ''));
        $templateName = $modelData['template_name'] ?? null;
        $upload       = $modelData['image'] ?? $modelData['file'] ?? null;

        if (!$templateName && !$metaChatSession->can_send_non_template_message) {
            return [
                'ok'      => false,
                'message' => __('The customer has not messaged in the last 24 hours. Only template messages can be sent.'),
                'code'    => 422,
            ];
        }

        $metadata            = [];
        $templateHeaderMedia = null;

        if ($templateName) {
            $resolved = $this->resolveTemplateParameters(
                $metaChatSession,
                $agent,
                $templateName,
                $modelData['template_language'],
                array_values($modelData['template_parameters'] ?? [])
            );

            if (!$resolved['ok']) {
                return $resolved;
            }

            $parameters = $resolved['parameters'];

            $built = $this->templatePayload(
                $metaChatSession,
                $to,
                $templateName,
                $modelData['template_language'],
                $parameters,
                $phoneNumberId,
                $accessToken
            );

            if (!$built['ok']) {
                return $built;
            }

            $payload            = $built['payload'];
            $templateHeaderMedia = $built['header_media'] ?? null;
            $messageText  = $this->renderTemplateBody($metaChatSession, $templateName, $modelData['template_language'], $parameters);
            $metadata['template'] = $templateName;
            $metadata['template_parameters'] = $parameters;
        } elseif ($upload instanceof UploadedFile) {
            $mediaResult = $this->uploadMedia($phoneNumberId, $accessToken, $upload);
            if (!$mediaResult['ok']) {
                return $mediaResult;
            }
            $isImage = isset($modelData['image']);
            $payload = [
                'messaging_product'          => 'whatsapp',
                'to'                         => $to,
                'type'                       => $isImage ? 'image' : 'document',
                ($isImage ? 'image' : 'document') => array_filter([
                    'id'       => $mediaResult['media_id'],
                    'caption'  => $messageText ?: null,
                    'filename' => $isImage ? null : $upload->getClientOriginalName(),
                ]),
            ];
            $metadata['wa_media_id'] = $mediaResult['media_id'];
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $messageText],
            ];
        }

        $repliedTo = $this->resolveRepliedTo($metaChatSession, $modelData['replied_to_id'] ?? null);

        if ($repliedTo) {
            $payload['context'] = ['message_id' => $repliedTo->meta_message_id];
        }

        $response = Http::withToken($accessToken)->post(
            $this->whatsappEndpoint($phoneNumberId.'/messages'),
            $payload
        );

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Failed to send WhatsApp message.'),
                'code'    => 422,
            ];
        }

        $metaMessageId = Arr::get($response->json(), 'messages.0.id');

        $metadata['wa_status'] = 'sent';

        $metaChatMessage = StoreMetaChatMessage::run($metaChatSession, [
            'meta_message_id' => $metaMessageId,
            'message_type'    => $templateHeaderMedia
                ? $this->headerMessageType($templateHeaderMedia)
                : ChatMessageTypeEnum::TEXT,
            'sender_type'     => ChatSenderTypeEnum::AGENT,
            'sender_id'       => $agent->id,
            'message_text'    => $messageText ?: null,
            'media_id'        => $templateHeaderMedia?->id,
            'replied_to_id'   => $repliedTo?->id,
            'metadata'        => $metadata,
        ]);

        if ($upload instanceof UploadedFile) {
            $this->processAttachment($metaChatMessage, $upload, isset($modelData['image']));
        }

        $metaChatSession->update(['last_agent_message_at' => now()]);

        $metaChatMessage = $metaChatMessage->fresh(['attachment', 'metaChatSession']);

        BroadcastRealtimeMetaChat::dispatch($metaChatMessage);
        BroadcastMetaChatListEvent::dispatch($metaChatMessage, $metaChatSession->fresh());

        return [
            'ok'   => true,
            'data' => $metaChatMessage,
            'code' => 201,
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(string $organisation, MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        $senderResult = $this->determineSenderData();

        if (!$senderResult['ok']) {
            return $senderResult;
        }

        return $this->handle($metaChatSession, $senderResult['data']['agent'], $request->validated());
    }

    protected function determineSenderData(): array
    {
        $user = Auth::user();

        if (!$user) {
            return [
                'ok'      => false,
                'message' => __('Only authenticated agents can send chats'),
                'code'    => 403,
            ];
        }

        $agent = ChatAgent::where('user_id', $user->id)->first();

        if (!$agent) {
            return [
                'ok'      => false,
                'message' => __('Only agents can send messages.'),
                'code'    => 403,
            ];
        }

        return [
            'ok'   => true,
            'data' => [
                'sender_type' => ChatSenderTypeEnum::AGENT->value,
                'sender_id'   => $agent->id,
                'agent'       => $agent,
            ],
        ];
    }


    protected function resolveRepliedTo(MetaChatSession $metaChatSession, ?int $repliedToId): ?MetaChatMessage
    {
        if (!$repliedToId) {
            return null;
        }

        return $metaChatSession->messages()
            ->whereKey($repliedToId)
            ->whereNotNull('meta_message_id')
            ->first();
    }

    /**
     * A template authored with merge tags carries its own parameter list, so the agent
     * does not type anything: the values come from the conversation. Templates written
     * before merge tags existed still take whatever the agent filled in.
     *
     * @return array{ok: bool, parameters?: array<int, string>, message?: string, code?: int}
     */
    protected function resolveTemplateParameters(
        MetaChatSession $metaChatSession,
        ChatAgent $agent,
        string $templateName,
        string $language,
        array $manualParameters
    ): array {
        $template = MetaMessageTemplate::where('name', $templateName)
            ->where('language', $language)
            ->when($metaChatSession->shop_id, fn ($query) => $query->where('shop_id', $metaChatSession->shop_id))
            ->first();

        $tags = Arr::get($template?->data ?? [], 'merge_tags.body', []);

        if (!$tags) {
            return ['ok' => true, 'parameters' => $manualParameters];
        }

        $resolved = ResolveWhatsappTemplateTags::run($metaChatSession, $tags, $agent);

        if ($resolved['missing']) {
            return [
                'ok'      => false,
                'message' => __('This template needs :tags, which we do not have for this contact yet.', [
                    'tags' => implode(', ', $resolved['missing']),
                ]),
                'code'    => 422,
            ];
        }

        return ['ok' => true, 'parameters' => $resolved['values']];
    }

    /**
     * Meta matches the components sent against the ones the template was approved with,
     * and answers `(#132012) Parameter format does not match format in the created
     * template` when they differ. A template with a media header therefore has to carry a
     * header component on every send, not just a body.
     *
     * @return array{ok: bool, payload?: array<string, mixed>, message?: string, code?: int}
     */
    protected function templatePayload(
        MetaChatSession $metaChatSession,
        string $to,
        string $name,
        string $language,
        array $parameters,
        string $phoneNumberId,
        string $accessToken
    ): array {
        $record = MetaMessageTemplate::where('name', $name)
            ->where('language', $language)
            ->when($metaChatSession->shop_id, fn ($query) => $query->where('shop_id', $metaChatSession->shop_id))
            ->first();

        $components  = [];
        $headerMedia = null;
        $header      = collect(Arr::get($record?->data ?? [], 'components', []))->firstWhere('type', 'HEADER');
        $format      = Arr::get($header ?? [], 'format', 'TEXT');

        if ($header && in_array($format, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            $headerComponent = $this->mediaHeaderComponent($record, $format, $phoneNumberId, $accessToken);

            if (!$headerComponent['ok']) {
                return $headerComponent;
            }

            $components[]  = $headerComponent['component'];
            $headerMedia   = $headerComponent['media'];
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
     * @return array{ok: bool, component?: array<string, mixed>, message?: string, code?: int}
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

    protected function renderTemplateBody(MetaChatSession $metaChatSession, string $name, string $language, array $parameters): string
    {
        $template = MetaMessageTemplate::where('name', $name)
            ->where('language', $language)
            ->when($metaChatSession->shop_id, fn ($query) => $query->where('shop_id', $metaChatSession->shop_id))
            ->first();

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

    protected function uploadMedia(string $phoneNumberId, string $accessToken, UploadedFile $file): array
    {
        return $this->uploadMediaFromPath(
            $phoneNumberId,
            $accessToken,
            $file->getPathName(),
            $file->getMimeType(),
            $file->getClientOriginalName()
        );
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

    protected function processAttachment(MetaChatMessage $metaChatMessage, UploadedFile $file, bool $isImage): void
    {
        $fileData = [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'checksum'     => md5_file($file->getPathName()),
        ];

        $media = StoreMediaFromFile::run(
            $metaChatMessage,
            $fileData,
            $isImage ? 'chat_images' : 'chat_attachments',
            $isImage ? 'image' : 'file'
        );

        $metaChatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => $isImage ? ChatMessageTypeEnum::IMAGE : ChatMessageTypeEnum::FILE,
        ]);
    }

    public function jsonResponse(array $result): JsonResponse
    {
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 400);
        }

        return response()->json([
            'success' => true,
            'data'    => MetaChatMessageResource::make($result['data']),
        ], $result['code'] ?? 201);
    }
}
