<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Http\Resources\CRM\Livechat\MetaChatMessageResource;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatSession;
use App\Models\Chat\MetaMessageTemplate;
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
                File::image()->max(10 * 1024)
            ],
            'file'                  => [
                'sometimes',
                'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'pptx'])
                    ->max(10 * 1024)
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
        ];
    }

    /**
     * @throws \Throwable
     */
    public function handle(MetaChatSession $metaChatSession, ChatAgent $agent, array $modelData): array
    {
        $shop          = $metaChatSession->shop;
        $phoneNumberId = (string) (Arr::get($shop?->settings, 'whatsapp.phone_number_id') ?: config('meta.whatsapp.phone_number_id'));
        $accessToken   = (string) (Arr::get($shop?->organisation?->settings, 'meta.access_key') ?: config('meta.whatsapp.access_token'));

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

        $metadata = [];

        if ($templateName) {
            $parameters   = array_values($modelData['template_parameters'] ?? []);
            $payload      = $this->templatePayload($to, $templateName, $modelData['template_language'], $parameters);
            $messageText  = $this->renderTemplateBody($metaChatSession, $templateName, $modelData['template_language'], $parameters);
            $metadata['template'] = $templateName;
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

        $response = Http::withToken($accessToken)->post(
            sprintf('%s/%s/%s/messages', config('meta.base_endpoint'), config('meta.whatsapp.api_version'), $phoneNumberId),
            $payload
        );

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Failed to send WhatsApp message.'),
                'code'    => 422,
            ];
        }

        $metadata['wa_message_id'] = Arr::get($response->json(), 'messages.0.id');

        $metaChatMessage = MetaChatMessage::create([
            'meta_channel_id'      => $metaChatSession->meta_channel_id,
            'meta_chat_session_id' => $metaChatSession->id,
            'message_type'         => ChatMessageTypeEnum::TEXT,
            'sender_type'          => ChatSenderTypeEnum::AGENT,
            'sender_id'            => $agent->id,
            'message_text'         => $messageText ?: null,
            'is_read'              => false,
            'metadata'             => $metadata,
        ]);

        if ($upload instanceof UploadedFile) {
            $this->processAttachment($metaChatMessage, $upload, isset($modelData['image']));
        }

        $metaChatSession->update(['last_agent_message_at' => now()]);

        return [
            'ok'   => true,
            'data' => $metaChatMessage->fresh('attachment'),
            'code' => 201,
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): array
    {
        $agent = Auth::id() ? ChatAgent::where('user_id', Auth::id())->first() : null;

        if (!$agent) {
            return [
                'ok'      => false,
                'message' => __('Only agents can send messages.'),
                'code'    => 403,
            ];
        }

        return $this->handle($metaChatSession, $agent, $request->validated());
    }

    protected function templatePayload(string $to, string $name, string $language, array $parameters): array
    {
        $template = [
            'name'     => $name,
            'language' => ['code' => $language],
        ];

        if ($parameters) {
            $template['components'] = [[
                'type'       => 'body',
                'parameters' => array_map(fn (string $text) => ['type' => 'text', 'text' => $text], $parameters),
            ]];
        }

        return [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $template,
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
        $response = Http::withToken($accessToken)
            ->attach('file', file_get_contents($file->getPathName()), $file->getClientOriginalName(), ['Content-Type' => $file->getMimeType()])
            ->post(
                sprintf('%s/%s/%s/media', config('meta.base_endpoint'), config('meta.whatsapp.api_version'), $phoneNumberId),
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
