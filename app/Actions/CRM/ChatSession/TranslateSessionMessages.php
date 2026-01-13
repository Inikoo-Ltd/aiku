<?php

namespace App\Actions\CRM\ChatSession;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Lorisleiva\Actions\ActionRequest;
use App\Events\TranslationChatIndicator;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Enums\CRM\Livechat\ChatSenderTypeEnum;

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

        if ($messages->isEmpty()) {
            TranslationChatIndicator::dispatch($chatSession, $targetLanguageId);
        }

        $jobs = [];

        foreach ($messages as $message) {
            $jobs[] = TranslateChatMessage::makeJob(
                $message->id,
                $targetLanguageId,
                'translate-session'
            );
        }


        $chatSessionId = $chatSession->id;

        $jobs[] = function () use ($chatSessionId, $targetLanguageId) {


            $session = ChatSession::find($chatSessionId);

            if ($session) {
                TranslationChatIndicator::dispatch($session, $targetLanguageId);
            }
        };

        Bus::chain($jobs)->dispatch();
    }

    public function asController(ActionRequest $request, ChatSession $chatSession): JsonResponse
    {
        $validatedData = $request->validated();

        $this->handle($chatSession, $validatedData['target_language_id']);

        return response()->json([
            'success' => true,
            'message' => 'Session messages translation processed successfully',
        ]);
    }

    public function rules(): array
    {
        return [
            'target_language_id' => 'required|exists:languages,id',
        ];
    }
}
