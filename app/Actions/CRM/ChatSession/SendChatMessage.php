<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\Comms\Email\SendChatNotificationToExternal;
use App\Actions\Comms\Email\StoreExternalEmailRecipient;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Events\BroadcastRealtimeChat;
use App\Events\BroadcastChatListEvent;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Illuminate\Validation\Rules\File;
use Illuminate\Http\UploadedFile;
use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\Comms\Email\SendChatNotificationToCustomer;

class SendChatMessage
{
    use AsAction;

    public function handle(ChatSession $chatSession, array $modelData): ChatMessage
    {
        $rawMessage = $modelData['message_text'] ?? '';
        if ($rawMessage !== null) {
            $sanitizedMessage = strip_tags($rawMessage);
            $sanitizedMessage = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $sanitizedMessage);
            $sanitizedMessage = trim($sanitizedMessage);
        } else {
            $sanitizedMessage = null;
        }
        $exists = ChatMessage::where('chat_session_id', $chatSession->id)
            ->where('sender_type', $modelData['sender_type'])
            ->where('message_text', $sanitizedMessage ?? '')
            ->whereBetween('created_at', [now()->subSeconds(1), now()])
            ->first();

        if ($exists) {
            return $exists;
        }


        $chatMessageData = [
            'chat_session_id' => $chatSession->id,
            'message_type'    => $modelData['message_type'] ?? ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => $modelData['sender_type'],
            'sender_id'       => $modelData['sender_id'] ?? null,
            'message_text'    => $sanitizedMessage,
            'media_id'        => $modelData['media_id'] ?? null,
            'original_text'   => $sanitizedMessage,
            'media_id'        => $modelData['media_id'] ?? null,
            'is_read'         => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        $chatMessage = ChatMessage::create($chatMessageData);

        if (isset($modelData['image']) && $modelData['image'] instanceof UploadedFile) {
            $this->processMessageImage($chatMessage, $modelData['image']);
        }
        if (isset($modelData['file']) && $modelData['file'] instanceof UploadedFile) {
            $this->processMessageFile($chatMessage, $modelData['file']);
        }

        $this->updateSessionTimestamps(
            $chatSession,
            $modelData['sender_type']
        );

        $this->logMessageEvent(
            $chatSession,
            $modelData['sender_type'],
            $modelData['sender_id'] ?? null,
            $chatMessage
        );

        TranslateChatMessage::dispatch(messageId: $chatMessage->id);
        BroadcastRealtimeChat::dispatch($chatMessage);
        BroadcastChatListEvent::dispatch($chatMessage);

        $shouldNotifyByEmail = $modelData['is_email_notif'] ?? false;

        if ($shouldNotifyByEmail && $modelData['sender_type'] === ChatSenderTypeEnum::AGENT->value) {
            $this->sendExternalNotification($chatSession);
        }

        return $chatMessage;
    }

    protected function processMessageImage(ChatMessage $chatMessage, UploadedFile $file): void
    {
        $imageData = [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'checksum'     => md5_file($file->getPathName()),
        ];

        $media = StoreMediaFromFile::run($chatMessage, $imageData, 'chat_images', 'image');

        $chatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => ChatMessageTypeEnum::IMAGE,
        ]);

        $chatMessage->refresh();
    }

    protected function processMessageFile(ChatMessage $chatMessage, UploadedFile $file): void
    {
        $fileData = [
            'path'         => $file->getPathName(),
            'originalName' => $file->getClientOriginalName(),
            'extension'    => $file->getClientOriginalExtension(),
            'checksum'     => md5_file($file->getPathName()),
        ];

        $media = StoreMediaFromFile::run($chatMessage, $fileData, 'chat_attachments', 'file');

        $chatMessage->updateQuietly([
            'media_id'     => $media->id,
            'message_type' => ChatMessageTypeEnum::FILE,
        ]);

        $chatMessage->refresh();
    }

    protected function updateSessionTimestamps(ChatSession $chatSession, string $senderType): void
    {
        $updateData = [];

        if ($senderType === ChatSenderTypeEnum::GUEST->value || $senderType === ChatSenderTypeEnum::USER->value) {
            data_set($updateData, 'last_visitor_message_at', now());
        } elseif ($senderType === ChatSenderTypeEnum::AGENT->value) {
            data_set($updateData, 'last_agent_message_at', now());
        }

        if (!empty($updateData)) {
            $chatSession->update($updateData);
        }
    }

    protected function logMessageEvent(ChatSession $chatSession, string $senderType, ?int $senderId, ChatMessage $message): void
    {
        $actorType = match ($senderType) {
            ChatActorTypeEnum::AGENT->value => ChatActorTypeEnum::AGENT,
            ChatSenderTypeEnum::USER->value => ChatActorTypeEnum::USER,
            default => ChatActorTypeEnum::GUEST
        };

        $isGuestMessage = in_array($senderType, [ChatActorTypeEnum::GUEST->value, ChatActorTypeEnum::USER->value]);

        StoreChatEvent::make()->messageSent(
            $chatSession,
            $actorType,
            $senderId,
            $message->id,
            $message->message_type->value,
            $isGuestMessage
        );
    }

    protected function sendExternalNotification(ChatSession $chatSession): void
    {
        $chatLink = null;
        if ($chatSession->shop && $chatSession->shop->website && $chatSession->ulid) {
            $chatLink = 'https://ds.test/?chat_session=' . $chatSession->ulid;
        }
        if ($chatSession->web_user_id && $chatSession->webUser && $chatSession->webUser->customer) {
            SendChatNotificationToCustomer::dispatch($chatSession->webUser->customer, ['chat_link' => $chatLink]);
            return;
        }

        if (!$chatSession->shop) {
            return;
        }

        $metadata = $chatSession->metadata ?? [];

        $email = $metadata['email'] ?? null;
        $name = $metadata['name'] ?? null;

        if (!$email) {
            return;
        }

        $externalEmailRecipient = StoreExternalEmailRecipient::run($chatSession->shop, [
            'name' => $name ?? $email,
            'email' => $email,
        ]);

        SendChatNotificationToExternal::dispatch($externalEmailRecipient, $chatSession->shop, ['chat_link' => $chatLink]);
    }

    public function rules(): array
    {
        return [
            'message_text' => [
                'required_without_all:image,file',
                'nullable',
                'string',
                'max:5000'
            ],
            'message_type' => [
                'required',
                Rule::enum(ChatMessageTypeEnum::class)
            ],
            'sender_id' => [
                'nullable',
                'integer',
                Rule::exists('web_users', 'id'),
            ],
            'sender_type' => [
                'sometimes',
                Rule::enum(ChatSenderTypeEnum::class)
            ],
            'image' => [
                'sometimes',
                'nullable',
                File::image()->max(10 * 1024)
            ],
            'file' => [
                'sometimes',
                'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'pptx'])
                    ->max(20 * 1024)
            ],
            'is_email_notif' => [
                'sometimes', 'nullable', 'in:true,false'
            ]
        ];
    }

    public function asController(Request $request): array
    {
        /** @var Organisation|null $organisation */
        $organisation = $request->route('organisation');

        /** @var ChatSession|string $chatSession */
        $chatSession = $request->route('chatSession');

        $ulid = is_string($chatSession)
            ? $chatSession
            : $chatSession->ulid;


        $this->validateUlid($ulid);

        $validated = $request->validate($this->rules());

        $chatSession = ChatSession::where('ulid', $ulid)->firstOrFail();

        $senderResult = $this->determineSenderData($validated, $chatSession);

        if (! $senderResult['ok']) {
            return $senderResult;
        }

        $validated = array_merge($validated, $senderResult['data']);

        $chatMessage = $this->handle($chatSession, $validated);

        return [
            'ok' => true,
            'data' => $chatMessage,
            'code' => 201,
        ];
    }

    protected function validateUlid($ulid): void
    {
        validator(
            ['session_ulid' => $ulid],
            $this->ulidRules()
        )->validate();
    }


    protected function ulidRules(): array
    {
        return [
            'session_ulid' => [
                'required',
                'string',
                'ulid',
                Rule::exists('chat_sessions', 'ulid')
            ]
        ];
    }



    protected function determineSenderData(array $validated, ChatSession $chatSession): array
    {
        $senderType = $validated['sender_type'] ?? null;

        if ($senderType === ChatSenderTypeEnum::SYSTEM->value) {
            return [
                'ok' => true,
                'data' => [
                    'sender_type' => ChatSenderTypeEnum::SYSTEM->value,
                    'sender_id'   => null,
                ],
            ];
        }
        if ($senderType === ChatSenderTypeEnum::AGENT->value) {

            $user = Auth::user();

            if (! $user) {
                return [
                    'ok' => false,
                    'message' => 'Only authenticated agents can send chats',
                    'code' => 403,
                ];
            }

            $agent = ChatAgent::where('user_id', $user->id)->first();

            if (! $agent) {
                return [
                    'ok' => false,
                    'message' => 'Only agents can send messages.',
                    'code' => 403,
                ];
            }

            $isAssigned = $chatSession->assignments()
                ->where('chat_agent_id', $agent->id)
                ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
                ->exists();

            if (! $isAssigned) {
                return [
                    'ok' => false,
                    'message' => 'Agent is not assigned to this chat session.',
                    'code' => 403,
                ];
            }

            return [
                'ok' => true,
                'data' => [
                    'sender_type' => ChatSenderTypeEnum::AGENT->value,
                    'sender_id'   => $agent->id,
                ],
            ];
        }

        if (!empty($validated['sender_id']) && $senderType === ChatSenderTypeEnum::USER->value) {

            $webUser = WebUser::find($validated['sender_id']);

            if ($webUser) {
                $chatSession->update([
                    'web_user_id' => $webUser->id,
                    'updated_at'  => now(),
                ]);

                return [
                    'ok' => true,
                    'data' => [
                        'sender_type' => ChatSenderTypeEnum::USER->value,
                        'sender_id'   => $webUser->id,
                    ],
                ];
            }
        }

        if ($senderType === ChatSenderTypeEnum::GUEST->value && $chatSession->web_user_id) {
            if ($chatSession->web_user_id) {
                return [
                    'ok' => true,
                    'data' => [
                        'sender_type' => ChatSenderTypeEnum::USER->value,
                        'sender_id'   => $chatSession->web_user_id,
                    ],
                ];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'sender_type' => ChatSenderTypeEnum::GUEST->value,
                'sender_id'   => null,
            ],
        ];
    }


    public function jsonResponse($result): JsonResponse
    {
        if (! $result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code'] ?? 400);
        }

        /** @var ChatMessage $message */
        $message = $result['data'];

        return response()->json([
            'success'    => true,
            'message'    => 'Message sent successfully',
            'message_id' => $message->id,
        ], $result['code'] ?? 200);
    }
}
