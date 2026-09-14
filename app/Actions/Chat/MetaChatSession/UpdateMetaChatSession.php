<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\MetaChatSession;

use App\Enums\CRM\Livechat\ChatActorTypeEnum;
use App\Enums\CRM\Livechat\ChatEventTypeEnum;
use App\Models\Chat\MetaChatSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The WhatsApp counterpart of UpdateChatSession. Priority has its own dedicated
 * action (SetMetaChatSessionPriority), so this only carries the rating.
 */
class UpdateMetaChatSession
{
    use AsAction;

    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', 'min:1', 'max:5'],
        ];
    }

    public function handle(MetaChatSession $metaChatSession, array $data, ?int $agentId = null): array
    {
        $metaChatSession->update(['rating' => $data['rating']]);

        StoreMetaChatEvent::make()->handle(
            $metaChatSession,
            ChatEventTypeEnum::RATING,
            $agentId ? ChatActorTypeEnum::AGENT : ChatActorTypeEnum::GUEST,
            $agentId,
            [
                'action_type'    => 'rating',
                'updated_fields' => ['rating'],
                'values'         => ['rating' => $data['rating']],
                'timestamp'      => now()->toISOString(),
            ]
        );

        return ['rating' => $data['rating']];
    }

    public function asController(MetaChatSession $metaChatSession, ActionRequest $request): JsonResponse
    {
        $updatedFields = $this->handle(
            $metaChatSession,
            $request->validated(),
            Auth::user()?->chatAgent?->id
        );

        return response()->json([
            'success' => true,
            'message' => 'Rating updated successfully',
            'data'    => [
                'updated_fields' => $updatedFields,
            ],
        ]);
    }
}
