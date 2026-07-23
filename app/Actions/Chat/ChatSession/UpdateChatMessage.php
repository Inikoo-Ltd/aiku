<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastRealtimeChat;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateChatMessage
{
    use AsAction;

    public const EDIT_WINDOW_MINUTES = 30;

    public function rules(): array
    {
        return [
            'message_text' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ChatSession $chatSession, ChatMessage $chatMessage, ChatAgent $agent, string $messageText): ChatMessage
    {
        if ($chatMessage->chat_session_id !== $chatSession->id) {
            throw ValidationException::withMessages([
                'message' => __('Message does not belong to this chat session'),
            ]);
        }

        if ($chatMessage->sender_type !== ChatSenderTypeEnum::AGENT || (int) $chatMessage->sender_id !== $agent->id) {
            throw ValidationException::withMessages([
                'message' => __('You can only edit your own messages'),
            ]);
        }

        $handlesChat = $chatSession->assignments()
            ->where('chat_agent_id', $agent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->exists();

        if (!$handlesChat) {
            throw ValidationException::withMessages([
                'message' => __('You can only edit messages in chats you are handling'),
            ]);
        }

        if ($chatMessage->message_type !== ChatMessageTypeEnum::TEXT) {
            throw ValidationException::withMessages([
                'message' => __('Only text messages can be edited'),
            ]);
        }

        if ($chatMessage->created_at->lt(now()->subMinutes(self::EDIT_WINDOW_MINUTES))) {
            throw ValidationException::withMessages([
                'message' => __('This message can no longer be edited'),
            ]);
        }

        $chatMessage->update([
            'message_text' => $messageText,
            'original_text' => $chatMessage->original_text !== null ? $messageText : null,
            'edited_at' => now(),
        ]);

        $chatMessage->refresh();

        BroadcastRealtimeChat::dispatch($chatMessage);

        return $chatMessage;
    }

    public function asController(string $organisation, ChatSession $chatSession, ChatMessage $chatMessage, ActionRequest $request): JsonResponse
    {
        $user = Auth::user();
        $agent = $user ? ChatAgent::where('user_id', $user->id)->first() : null;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents can edit messages'),
            ], 403);
        }

        try {
            $chatMessage = $this->handle($chatSession, $chatMessage, $agent, $request->validated()['message_text']);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Message updated'),
            'data' => (new ChatMessageResource($chatMessage))->resolve(),
        ]);
    }
}
