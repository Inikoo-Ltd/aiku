<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\ChatSession;

use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Events\BroadcastChatReaction;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleChatMessageReaction
{
    use AsAction;

    /**
     * @param array{reactor_type: string, reactor_id: int|null} $reactor
     */
    public function handle(ChatMessage $chatMessage, array $reactor, string $emoji): ChatMessage
    {
        $query = $chatMessage->reactions()
            ->where('reactor_type', $reactor['reactor_type'])
            ->where('emoji', $emoji);

        if ($reactor['reactor_id'] !== null) {
            $query->where('reactor_id', $reactor['reactor_id']);
        } else {
            $query->whereNull('reactor_id');
        }

        $existing = $query->first();

        if ($existing) {
            $existing->delete();
        } else {
            $chatMessage->reactions()->create([
                'chat_session_id' => $chatMessage->chat_session_id,
                'reactor_type'    => $reactor['reactor_type'],
                'reactor_id'      => $reactor['reactor_id'],
                'emoji'           => $emoji,
            ]);
        }

        $chatMessage->load('reactions');

        BroadcastChatReaction::dispatch($chatMessage);

        return $chatMessage;
    }

    public function rules(): array
    {
        return [
            'emoji'        => ['required', 'string', 'max:16'],
            'reactor'      => ['required', 'string', Rule::in(['agent', 'customer'])],
            'session_ulid' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array{ok: bool, reactor?: array{reactor_type: string, reactor_id: int|null}, message?: string, code?: int}
     */
    protected function resolveReactor(ChatMessage $chatMessage, array $validated): array
    {
        if ($validated['reactor'] === 'agent') {
            $user = Auth::user();

            if (!$user) {
                return ['ok' => false, 'message' => 'Only authenticated agents can react.', 'code' => 403];
            }

            $agent = ChatAgent::where('user_id', $user->id)->first();

            if (!$agent) {
                return ['ok' => false, 'message' => 'Only agents can react.', 'code' => 403];
            }

            return [
                'ok'      => true,
                'reactor' => [
                    'reactor_type' => ChatSenderTypeEnum::AGENT->value,
                    'reactor_id'   => $user->id,
                ],
            ];
        }

        $chatSession = $chatMessage->chatSession;

        if (!$chatSession || ($validated['session_ulid'] ?? null) !== $chatSession->ulid) {
            return ['ok' => false, 'message' => 'Invalid chat session.', 'code' => 403];
        }

        if ($chatSession->web_user_id) {
            return [
                'ok'      => true,
                'reactor' => [
                    'reactor_type' => ChatSenderTypeEnum::USER->value,
                    'reactor_id'   => $chatSession->web_user_id,
                ],
            ];
        }

        return [
            'ok'      => true,
            'reactor' => [
                'reactor_type' => ChatSenderTypeEnum::GUEST->value,
                'reactor_id'   => null,
            ],
        ];
    }

    public function asController(ChatMessage $chatMessage, ActionRequest $request): JsonResponse
    {
        $validated = validator($request->all(), $this->rules())->validate();

        $resolved = $this->resolveReactor($chatMessage, $validated);

        if (!$resolved['ok']) {
            return response()->json(['message' => $resolved['message']], $resolved['code']);
        }

        $chatMessage = $this->handle($chatMessage, $resolved['reactor'], $validated['emoji']);

        return response()->json([
            'data' => new ChatMessageResource($chatMessage),
        ]);
    }
}
