<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed Aug 19 2026
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Actions\Chat\ChatSession\SettleChatAiDraft;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappTemplatePayload;
use App\Actions\Chat\Whatsapp\NormaliseWhatsappVideo;
use App\Actions\Chat\Whatsapp\StoreMetaTrackingEvent;
use App\Actions\Chat\Whatsapp\Templates\ResolveWhatsappTemplateTags;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Enums\CRM\Livechat\ChatAssignmentAssignedByEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatSessionStatusEnum;
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
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
    use WithChatAgentAuthorisation;

    /**
     * What a phone records, not what WhatsApp plays: every one of these is converted to an
     * H.264 MP4 before it is sent.
     */
    public const VIDEO_UPLOAD_EXTENSIONS = ['mp4', 'mov', 'm4v', '3gp', 'webm', 'avi', 'mkv'];

    public function rules(): array
    {
        return [
            'message_text'          => [
                'required_without_all:image,video,file,template_name',
                'nullable',
                'string',
                'max:4096',
                fn (string $attribute, mixed $value, \Closure $fail) => is_string($value) && preg_match(\App\Actions\Chat\ChatSession\DraftChatReply::GAP, $value)
                    ? $fail(__('Fill in or delete the parts marked [[ ]] before sending.'))
                    : null,
            ],
            'image'                 => [
                'sometimes',
                'nullable',
                File::types(WhatsappMediaTypeEnum::IMAGE->extensions())
                    ->max(WhatsappMediaTypeEnum::IMAGE->maxKilobytes())
            ],
            'video'                 => [
                'sometimes',
                'nullable',
                File::types(self::VIDEO_UPLOAD_EXTENSIONS)
                    ->max(WhatsappMediaTypeEnum::DOCUMENT->maxKilobytes())
            ],
            'file'                  => [
                'sometimes',
                'nullable',
                File::types(WhatsappMediaTypeEnum::DOCUMENT->extensions())
                    ->max(WhatsappMediaTypeEnum::DOCUMENT->maxKilobytes())
            ],
            'template_id'           => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('meta_message_templates', 'id')
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
        $video = $modelData['video'] ?? null;

        if (!$video instanceof UploadedFile) {
            return $this->send($metaChatSession, $agent, $modelData);
        }

        $converted = $this->videoForWhatsapp($video);

        if (!$converted['ok']) {
            return $converted;
        }

        try {
            return $this->send($metaChatSession, $agent, $modelData, $converted);
        } finally {
            @unlink($converted['path']);
        }
    }

    /**
     * @param  array{path: string, mime: string, name: string, extension: string}|null  $convertedVideo
     *
     * @throws \Throwable
     */
    protected function send(MetaChatSession $metaChatSession, ChatAgent $agent, array $modelData, ?array $convertedVideo = null): array
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

        $to          = preg_replace('/\D/', '', (string) $metaChatSession->phone_number);
        $messageText = trim(strip_tags($modelData['message_text'] ?? ''));
        $upload      = $modelData['image'] ?? $modelData['video'] ?? $modelData['file'] ?? null;
        $mediaKind   = match (true) {
            isset($modelData['image']) => WhatsappMediaTypeEnum::IMAGE,
            isset($modelData['video']) => WhatsappMediaTypeEnum::VIDEO,
            default                    => WhatsappMediaTypeEnum::DOCUMENT,
        };

        /* Resolved once here so the name and language used for the rest of the send come from
           the record itself: a request carrying only an id does not send them, and a request
           carrying only a name may be naming one of several templates that share it. */
        $template = $this->findTemplate(
            $modelData['template_id'] ?? null,
            $metaChatSession->shop_id,
            $modelData['template_name'] ?? null,
            $modelData['template_language'] ?? null
        );

        $templateName     = $template?->name ?? ($modelData['template_name'] ?? null);
        $templateLanguage = $template?->language ?? ($modelData['template_language'] ?? null);

        if ($templateName && !$templateLanguage) {
            return [
                'ok'      => false,
                'message' => __('This template has no language set and cannot be sent.'),
                'code'    => 422,
            ];
        }

        if ($template?->isUsedByWhatsappOutbox()) {
            return [
                'ok'      => false,
                'message' => __('This template is used by a WhatsApp customer notification and cannot be sent from a chat.'),
                'code'    => 422,
            ];
        }

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
                $template,
                array_values($modelData['template_parameters'] ?? [])
            );

            if (!$resolved['ok']) {
                return $resolved;
            }

            $parameters = $resolved['parameters'];

            $built = $this->templatePayload(
                $template?->id,
                $metaChatSession->shop_id,
                $to,
                $templateName,
                $templateLanguage,
                $parameters,
                $phoneNumberId,
                $accessToken
            );

            if (!$built['ok']) {
                return $built;
            }

            $payload            = $built['payload'];
            $templateHeaderMedia = $built['header_media'] ?? null;
            $messageText  = $this->renderTemplateBody($template?->id, $metaChatSession->shop_id, $templateName, $templateLanguage, $parameters);
            $metadata['template'] = $templateName;
            $metadata['meta_message_template_id'] = $template?->id;
            $metadata['template_parameters'] = $parameters;
        } elseif ($upload instanceof UploadedFile) {
            $attachment = $convertedVideo ?? [
                'path'      => $upload->getPathname(),
                'mime'      => $upload->getMimeType(),
                'name'      => $upload->getClientOriginalName(),
                'extension' => $upload->getClientOriginalExtension(),
            ];

            $mediaResult = $this->uploadMediaFromPath($phoneNumberId, $accessToken, $attachment['path'], $attachment['mime'], $attachment['name']);
            if (!$mediaResult['ok']) {
                return $mediaResult;
            }
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => $mediaKind->value,
                $mediaKind->value   => array_filter([
                    'id'       => $mediaResult['media_id'],
                    'caption'  => $messageText ?: null,
                    'filename' => $mediaKind === WhatsappMediaTypeEnum::DOCUMENT ? $attachment['name'] : null,
                ]),
            ];
            $metadata['wa_media_id'] = $mediaResult['media_id'];
            $metadata['wa_type']     = $mediaKind->value;
        } else {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $messageText],
            ];
        }

        unset($payload['to']);
        $payload = array_merge($payload, $metaChatSession->whatsappRecipient());

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

        if (isset($attachment)) {
            $this->processAttachment($metaChatMessage, $attachment, $mediaKind === WhatsappMediaTypeEnum::IMAGE);
        }

        $metaChatSession->update(['last_agent_message_at' => now()]);

        SettleChatAiDraft::run($metaChatSession, $metaChatMessage);

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
        $senderResult = $this->determineSenderData($metaChatSession);

        if (!$senderResult['ok']) {
            return $senderResult;
        }

        return $this->handle($metaChatSession, $senderResult['data']['agent'], $request->validated());
    }

    protected function determineSenderData(MetaChatSession $metaChatSession): array
    {
        $user = Auth::user();

        if (!$user instanceof User) {
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

        $isAssigned = $metaChatSession->assignments()
            ->where('chat_agent_id', $agent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->exists();

        if (!$isAssigned) {
            $claim = $this->claimUnheldChat($metaChatSession, $user, $agent);

            if ($claim !== null) {
                return $claim;
            }
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


    /**
     * @return array{ok: bool, message: string, code: int}|null  a refusal, or null once claimed
     */
    private function claimUnheldChat(MetaChatSession $metaChatSession, User $user, ChatAgent $agent): ?array
    {
        if ($metaChatSession->assignments()->where('status', ChatAssignmentStatusEnum::ACTIVE->value)->exists()) {
            return $this->heldByAnotherAgent($metaChatSession);
        }

        $shop = $metaChatSession->shop;

        if (!$shop instanceof Shop || !$this->userCanActOnChatOnShop($user, $shop)) {
            return [
                'ok'      => false,
                'message' => __('You do not work chat on this shop.'),
                'code'    => 403,
            ];
        }

        try {
            $metaChatSession->assignments()->create([
                'meta_channel_id' => $metaChatSession->meta_channel_id,
                'chat_agent_id'   => $agent->id,
                'status'          => ChatAssignmentStatusEnum::ACTIVE->value,
                'assigned_by'     => ChatAssignmentAssignedByEnum::AGENT->value,
                'note'            => 'Claimed by replying',
                'assigned_at'     => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->heldByAnotherAgent($metaChatSession);
        }

        $metaChatSession->update(['status' => ChatSessionStatusEnum::ACTIVE->value]);

        BroadcastMetaChatListEvent::dispatch(null, $metaChatSession->fresh());

        return null;
    }

    /**
     * @return array{ok: bool, message: string, code: int}
     */
    private function heldByAnotherAgent(MetaChatSession $metaChatSession): array
    {
        return [
            'ok'      => false,
            'message' => $this->chatHeldByAnotherAgentMessage($metaChatSession),
            'code'    => 403,
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
        ?MetaMessageTemplate $template,
        array $manualParameters
    ): array {
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

    /**
     * @return array{ok: true, path: string, mime: string, name: string, extension: string}|array{ok: false, message: string, code: int}
     */
    protected function videoForWhatsapp(UploadedFile $file): array
    {
        $converted = NormaliseWhatsappVideo::make()->transcode($file->getPathname());

        if ($converted === null) {
            return [
                'ok'      => false,
                'message' => __('This video could not be converted for WhatsApp. Send a shorter clip, or send it by email.'),
                'code'    => 422,
            ];
        }

        if (filesize($converted) > WhatsappMediaTypeEnum::VIDEO->maxKilobytes() * 1024) {
            @unlink($converted);

            return [
                'ok'      => false,
                'message' => __('This video is still over 16MB once compressed, which is the most WhatsApp takes. Send a shorter clip, or send it by email.'),
                'code'    => 422,
            ];
        }

        return [
            'ok'        => true,
            'path'      => $converted,
            'mime'      => 'video/mp4',
            'name'      => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.mp4',
            'extension' => 'mp4',
        ];
    }

    /**
     * @param  array{path: string, name: string, extension: string}  $attachment
     */
    protected function processAttachment(MetaChatMessage $metaChatMessage, array $attachment, bool $isImage): void
    {
        $fileData = [
            'path'         => $attachment['path'],
            'originalName' => $attachment['name'],
            'extension'    => $attachment['extension'],
            'checksum'     => md5_file($attachment['path']),
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
