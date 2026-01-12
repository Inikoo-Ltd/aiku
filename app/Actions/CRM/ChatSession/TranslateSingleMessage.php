<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatMessage;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Actions\CRM\ChatSession\TranslateChatMessage;

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

        TranslateChatMessage::run($chatMessage, $targetLanguageId);
    }

    public function asController(Request $request, ChatMessage $chatMessage): JsonResponse
    {
        $request->validate([
            'target_language_id' => 'required|exists:languages,id',
        ]);

        $this->handle($chatMessage, $request->target_language_id);

        $chatMessage->load('translations.targetLanguage');

        return response()->json([
            'success' => true,
            'message' => 'Message translation processing started',
            'data'    => $chatMessage
        ]);
    }
}
