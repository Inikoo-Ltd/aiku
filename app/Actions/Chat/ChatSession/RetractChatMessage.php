<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use App\Enums\CRM\Livechat\ChatMessageTypeEnum;
use App\Enums\CRM\Livechat\ChatRetractionReasonEnum;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastChatMessageRetracted;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use App\Models\Chat\ChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Take back a message sent by mistake. The customer stops seeing it; we never do.
 * The row is soft deleted, which drops it from the public endpoint the widget reads
 * while leaving it in the agent's thread, greyed, with the time it was sent and the
 * time it was taken back.
 */
class RetractChatMessage
{
    use AsAction;

    public const RETRACT_WINDOW_MINUTES = UpdateChatMessage::EDIT_WINDOW_MINUTES;

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(ChatRetractionReasonEnum::class)],
            'note'   => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(
        ChatSession $chatSession,
        ChatMessage $chatMessage,
        ChatAgent $agent,
        ChatRetractionReasonEnum $reason,
        ?string $note = null
    ): ChatMessage {
        if ($chatMessage->chat_session_id !== $chatSession->id) {
            throw ValidationException::withMessages([
                'message' => __('Message does not belong to this chat session'),
            ]);
        }

        if ($chatMessage->sender_type !== ChatSenderTypeEnum::AGENT || (int) $chatMessage->sender_id !== $agent->id) {
            throw ValidationException::withMessages([
                'message' => __('You can only take back your own messages'),
            ]);
        }

        $handlesChat = $chatSession->assignments()
            ->where('chat_agent_id', $agent->id)
            ->where('status', ChatAssignmentStatusEnum::ACTIVE->value)
            ->exists();

        if (!$handlesChat) {
            throw ValidationException::withMessages([
                'message' => __('You can only take back messages in chats you are handling'),
            ]);
        }

        if ($chatMessage->message_type !== ChatMessageTypeEnum::TEXT) {
            throw ValidationException::withMessages([
                'message' => __('Only text messages can be taken back'),
            ]);
        }

        if ($chatMessage->created_at->lt(now()->subMinutes(self::RETRACT_WINDOW_MINUTES))) {
            throw ValidationException::withMessages([
                'message' => __('This message can no longer be taken back'),
            ]);
        }

        $metadata = $chatMessage->metadata ?? [];
        data_set($metadata, 'retracted_by_user_id', Auth::id());
        data_set($metadata, 'retracted_by_agent_id', $agent->id);
        data_set($metadata, 'retraction_reason', $reason->value);
        // The note is for our record of what happened, never for the customer to read.
        data_set($metadata, 'retraction_note', $note);

        $chatMessage->update(['metadata' => $metadata]);
        $chatMessage->delete();
        $chatMessage->refresh();

        BroadcastChatMessageRetracted::dispatch($chatMessage);

        return $chatMessage;
    }

    public function asController(string $organisation, ChatSession $chatSession, ChatMessage $chatMessage, ActionRequest $request): JsonResponse
    {
        $user  = Auth::user();
        $agent = $user ? ChatAgent::where('user_id', $user->id)->first() : null;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents can take back messages'),
            ], 403);
        }

        try {
            $validated = $request->validated();

            $chatMessage = $this->handle(
                $chatSession,
                $chatMessage,
                $agent,
                ChatRetractionReasonEnum::from($validated['reason']),
                $validated['note'] ?? null
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Message taken back'),
            'data'    => [
                'id'               => $chatMessage->id,
                'retracted_at'     => $chatMessage->deleted_at?->toISOString(),
                'retraction_reason' => ChatRetractionReasonEnum::from($chatMessage->metadata['retraction_reason'])->label(),
            ],
        ]);
    }
}
