<?php

namespace App\Actions\CRM\ChatSession;

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

class SendChatMessage
{
    use AsAction;

    public function handle(ChatSession $chatSession, array $modelData): ChatMessage
    {
        $exists = ChatMessage::where('chat_session_id', $chatSession->id)
            ->where('sender_type', $modelData['sender_type'])
            ->where('message_text', $modelData['message_text'])
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
            'message_text'    => $modelData['message_text'],
            'media_id'        => $modelData['media_id'] ?? null,
            'is_read'         => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ];

        $chatMessage = ChatMessage::create($chatMessageData);

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

        BroadcastRealtimeChat::dispatch($chatMessage);
        BroadcastChatListEvent::dispatch();

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

    public function asController(Request $request, string $ulid, ?string $organisation = null): ChatMessage
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
        $user = Auth::user();

        if ($user && ($agent = ChatAgent::where('user_id', $user->id)->first())) {
            return [
                'sender_type' => ChatSenderTypeEnum::AGENT->value,
                'sender_id' => $agent->id,
            ];
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
