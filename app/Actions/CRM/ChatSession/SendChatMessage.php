<?php

namespace App\Actions\CRM\ChatSession;

use App\Models\CRM\WebUser;
use App\Models\SysAdmin\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use App\Models\CRM\Livechat\ChatAgent;
use App\Models\CRM\Livechat\ChatEvent;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
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

        $chatMessage = ChatMessage::create($chatMessageData);

        $this->updateSessionTimestamps($chatSession, $modelData['sender_type']);

        $this->logMessageEvent($chatSession, $modelData['sender_type'], $modelData['sender_id'] ?? null, $chatMessage);

        return $chatMessage;
    }


    protected function updateSessionTimestamps(ChatSession $chatSession, string $senderType): void
    {
        $updateData = [];

        if ($senderType === ChatActorTypeEnum::GUEST->value || $senderType === ChatActorTypeEnum::USER->value) {
            $updateData['last_visitor_message_at'] = now();
        } elseif ($senderType === ChatActorTypeEnum::AGENT->value) {
            $updateData['last_agent_message_at'] = now();
        }

        if (!empty($updateData)) {
            $chatSession->update($updateData);
        }
    }


    protected function logMessageEvent(ChatSession $chatSession, string $senderType, ?int $senderId, ChatMessage $message): void
    {
        ChatEvent::create([
            'chat_session_id' => $chatSession->id,
            'event_type' => ChatEventTypeEnum::MESSAGE_SENT->value,
            'actor_type' => $senderType,
            'actor_id' => $senderId,
            'payload' => [
                'message_id' => $message->id,
                'message_type' => $message->message_type,
                'is_guest_message' => in_array($senderType, [ChatActorTypeEnum::GUEST->value, ChatActorTypeEnum::USER->value]),
            ],
        ]);
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
            'sender_type' => [
                'required',
                Rule::in([
                    ChatActorTypeEnum::GUEST->value,
                    ChatActorTypeEnum::USER->value,
                    ChatActorTypeEnum::AGENT->value,
                    ChatActorTypeEnum::SYSTEM->value,
                    ChatActorTypeEnum::AI->value,
                ])
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

    public function asController(ChatSession $chatSession, ActionRequest $request): ChatMessage
    {
        $senderData = $this->determineSenderData();

        $request->merge($senderData);

        return $this->handle($chatSession, $request->validated());
    }


    protected function determineSenderData(): array
    {
        if (auth()->check()) {
            $user = auth()->user();
            if ($user instanceof User) {
                $agent = ChatAgent::where('user_id', $user->id)->first();
                if ($agent) {
                    return [
                        'sender_type' => ChatActorTypeEnum::AGENT->value,
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
                        'sender_type' => ChatActorTypeEnum::USER->value,
                        'sender_id' => $webUser->id,
                    ];
                }
            }
        }

        return [
            'sender_type' => ChatActorTypeEnum::GUEST->value,
            'sender_id' => null,
        ];
    }


    public function htmlResponse(ChatMessage $chatMessage): RedirectResponse
    {
        return Redirect::route('chat.sessions.show', $chatMessage->chatSession->ulid)
            ->with('success', __('Message sent successfully'));
    }
}
