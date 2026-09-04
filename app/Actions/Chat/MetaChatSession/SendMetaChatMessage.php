<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed Aug 19 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappTemplatePayload;
use App\Actions\Chat\Whatsapp\StoreMetaTrackingEvent;
use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\MetaTrackingEventTypeEnum;
use App\Enums\CRM\Livechat\WhatsappMediaTypeEnum;
use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastMetaChatListEvent;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
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
    use WithWhatsappTemplatePayload;

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
                $metaChatSession->shop_id,
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
            $messageText  = $this->renderTemplateBody($metaChatSession->shop_id, $templateName, $modelData['template_language'], $parameters);
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
            $reason = Arr::get($response->json(), 'error.message') ?: __('Failed to send WhatsApp message.');

            /* The send never reached Meta, so no message row is stored and no status webhook
               will ever follow; the tracking event is the only record the attempt happened. */
            StoreMetaTrackingEvent::run(
                MetaTrackingEventTypeEnum::FAILED,
                data: ['error' => $reason, 'agent_id' => $agent->id],
                metaChatSession: $metaChatSession
            );

            return [
                'ok'      => false,
                'message' => $reason,
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
        $template = $this->findTemplate($metaChatSession->shop_id, $templateName, $language);

        $tags = Arr::get($template?->data ?? [], 'merge_tags.body', []);

        if (!$tags) {
            return ['ok' => true, 'parameters' => $manualParameters];
        }

        if (count($manualParameters) >= count($tags)) {
            $blanks = array_filter($manualParameters, fn ($v) => blank($v));
            if (!empty($blanks)) {
                return [
                    'ok'      => false,
                    'message' => __('All template parameters must have a value.'),
                    'code'    => 422,
                ];
            }

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

        return ['ok' => true, 'parameters' => array_filter($resolved['values'], fn ($v) => $v !== null)];
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
