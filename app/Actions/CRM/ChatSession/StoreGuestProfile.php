<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatSession;
use App\Models\CRM\Livechat\ChatMessage;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Events\BroadcastRealtimeChat;
use App\Events\BroadcastChatListEvent;

class StoreGuestProfile
{
    use AsAction;

    public function rules(): array
    {
        return [
            'name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'max:255', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'message_text' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function asController(ChatSession $chatSession, ActionRequest $request): array
    {
        $validated = $request->validated();
        return $this->handle($chatSession, $validated);
    }

    public function handle(ChatSession $chatSession, array $data): array
    {
        $actorType = $chatSession->web_user_id
            ? ChatActorTypeEnum::USER
            : ChatActorTypeEnum::GUEST;

        $actorId = $chatSession->web_user_id ?: null;

        $summary = $this->buildSummaryText($data);

        $chatMessage = ChatMessage::create([
            'chat_session_id' => $chatSession->id,
            'message_type'    => ChatMessageTypeEnum::TEXT->value,
            'sender_type'     => ChatSenderTypeEnum::SYSTEM->value,
            'sender_id'       => null,
            'message_text'    => $summary,
            'is_read'         => false,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $payload = [
            'name'             => $data['name'] ?? null,
            'email'            => $data['email'] ?? null,
            'phone'            => $data['phone'] ?? null,
            'chat_message_id'  => $chatMessage->id,
            'guest_identifier' => $chatSession->guest_identifier,
        ];

        StoreChatEvent::make()->customEvent(
            $chatSession,
            ChatEventTypeEnum::GUEST_PROFILE,
            $actorType,
            $actorId,
            $payload
        );

        BroadcastRealtimeChat::dispatch($chatMessage);
        BroadcastChatListEvent::dispatch();

        return [
            'message' => $chatMessage,
            'event_payload' => $payload,
        ];
    }

    protected function buildSummaryText(array $data): string
    {
        $name  = $data['name']  ?? '-';
        $email = $data['email'] ?? '-';
        if (!empty($data['phone'])) {
            $phone = $data['phone'] ?? '-';
            return "Guest profile submitted: Name: {$name}, Email: {$email}, Phone: {$phone}";
        }
        return "Guest profile submitted: Name: {$name}, Email: {$email}";
    }

    public function jsonResponse($result): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Guest profile submitted',
            'data'    => [
                'chat_message_id' => $result['message']->id ?? null,
                'event_payload'   => $result['event_payload'] ?? [],
            ]
        ], 201);
    }
}
