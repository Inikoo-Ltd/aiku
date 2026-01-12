<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;
use App\Actions\CRM\ChatSession\TranslateChatMessage;

class TranslateSessionMessages
{
    use AsAction;

    public function handle(ChatSession $chatSession, int $targetLanguageId): void
    {
        $messages = $chatSession->messages()
            ->whereIn('sender_type', [ChatSenderTypeEnum::USER, ChatSenderTypeEnum::GUEST])
            ->whereDoesntHave('translations', function ($query) use ($targetLanguageId) {
                $query->where('target_language_id', $targetLanguageId);
            })
            ->get();

        foreach ($messages as $message) {
            TranslateChatMessage::run($message, $targetLanguageId);
        }
    }

    public function asController(Request $request, ChatSession $chatSession): JsonResponse
    {
        $request->validate([
            'target_language_id' => 'required|exists:languages,id',
        ]);

        $this->handle($chatSession, $request->target_language_id);

        return response()->json([
            'success' => true,
            'message' => 'Session messages translation processing started in background',
        ]);
    }
}
