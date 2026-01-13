<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatMessage;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;

class TranslateSingleMessage
{
    use AsAction;

    public function handle(ChatMessage $chatMessage, int $targetLanguageId): void
    {
        $exists = $chatMessage->translations()
            ->where('target_language_id', $targetLanguageId)
            ->exists();

        if ($exists) {
            return;
        }

        TranslateChatMessage::dispatch(messageId: $chatMessage->id, targetLanguageId: $targetLanguageId, requestFrom: 'agent');
    }

    public function asController(ActionRequest $request, ChatMessage $chatMessage): JsonResponse
    {
        $validated = $request->validated();

        $this->handle($chatMessage, $validated['target_language_id']);

        $chatMessage->load(['translations.targetLanguage', 'originalLanguage']);

        return response()->json([
            'success' => true,
            'message' => 'Message translation processed successfully',
            'data'    => new ChatMessageResource($chatMessage)
        ]);
    }

    public function rules(): array
    {
        return [
            'target_language_id' => 'required|exists:languages,id',
        ];
    }
}
