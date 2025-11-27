<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;

use Illuminate\Http\JsonResponse;

use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Actions\CRM\ChatSession\StoreChatEvent;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;

class SendChatMessage
{
    use AsAction;

    public function handle(ChatSession $chatSession, array $modelData): ChatMessage
    {
        $chatMessageData = [];

        data_set($chatMessageData, 'chat_session_id', $chatSession->id);
        data_set($chatMessageData, 'message_type', $modelData['message_type'] ?? ChatMessageTypeEnum::TEXT->value);
        data_set($chatMessageData, 'sender_type', $modelData['sender_type']);
        data_set($chatMessageData, 'sender_id', $modelData['sender_id'] ?? null);
        data_set($chatMessageData, 'message_text', $modelData['message_text']);
        data_set($chatMessageData, 'media_id', $modelData['media_id'] ?? null);
        data_set($chatMessageData, 'is_read', false);
        data_set($chatMessageData, 'created_at', now());
        data_set($chatMessageData, 'updated_at', now());

        $chatMessage = ChatMessage::create($chatMessageData);

        $this->updateSessionTimestamps($chatSession, $modelData['sender_type']);

        $this->logMessageEvent($chatSession, $modelData['sender_type'], $modelData['sender_id'] ?? null, $chatMessage);

        return $chatMessage;
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
        $actorType = match($senderType) {
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

    public function rules(): array
    {
        return [
            'message_text' => [
                'required_without:media_id',
                'string',
                'max:5000'
            ],
            'message_type' => [
                'required',
                Rule::enum(ChatMessageTypeEnum::class)
            ],
            'sender_id' => [
                'nullable',
                'integer'
            ],
            'media_id' => [
                'nullable',
                'exists:media,id'
            ],
        ];
    }

    public function asController(Request $request, $ulid): ChatMessage
    {
        $this->validateUlid($ulid);

        $validated = $request->validate($this->rules());

        $chatSession = ChatSession::where('ulid', $ulid)->first();
        $senderData = $this->determineSenderData();
        $validated = array_merge($validated, $senderData);

        return $this->handle($chatSession, $validated);
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



    protected function determineSenderData(): array
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user instanceof User) {
                $agent = ChatAgent::where('user_id', $user->id)->first();
                if ($agent) {
                    return [
                        'sender_type' => ChatSenderTypeEnum::AGENT->value,
                        'sender_id' => $agent->id,
                    ];
                }
            }
        }

        $webUserGuards = ['retina', 'web'];
        foreach ($webUserGuards as $guard) {
            if (auth()->guard($guard)->check()) {
                $webUser = auth()->guard($guard)->user();
                if ($webUser instanceof WebUser) {
                    return [
                        'sender_type' => ChatSenderTypeEnum::USER->value,
                        'sender_id' => $webUser->id,
                    ];
                }
            }
        }

        return [
            'sender_type' => ChatSenderTypeEnum::GUEST->value,
            'sender_id' => null,
        ];
    }

    public function jsonResponse(ChatMessage $chatMessage): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'message_id' => $chatMessage->id
        ], 201);
    }
}