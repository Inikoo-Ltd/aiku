<?php

namespace App\Actions\CRM\ChatSession;

use OpenAI;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Log;
use App\Events\BroadcastRealtimeChat;
use App\Models\CRM\Livechat\ChatMessage;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Models\CRM\Livechat\ChatMessageTranslation;
use App\Enums\CRM\Livechat\ChatAssignmentStatusEnum;
use Sentry\Laravel\Facade as Sentry;
use Throwable;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Helpers\Translations\DetectLanguageWithAI;

class TranslateChatMessage
{
    use AsAction;

    public string $jobQueue = 'analytics';

    public function handle(
        int $messageId,
        ?int $targetLanguageId = null,
        ?string $requestFrom = null
    ): void {

        $message = ChatMessage::find($messageId);

        if (!$message) {
            Log::warning('Message not found', ['id' => $messageId]);
            return;
        }

        if (empty($message->original_text) && empty($message->message_text)) {
            return;
        }

        $textToProcess = $message->original_text ?? $message->message_text;
        $session = $message->chatSession;

        if ($this->isUserMessage($message)) {
            $this->handleUserLanguageDetection($message, $session, $textToProcess);
        }

        $targetLangId = $this->determineTargetLanguage(
            $message,
            $session,
            $targetLanguageId
        );


        if (!$targetLangId) {
            return;
        }

        if ($message->original_language_id === $targetLangId) {
            return;
        }

        $this->performTranslation($message, $textToProcess, $targetLangId, $requestFrom);

    }

    /**
     *
     */
    protected function isUserMessage(ChatMessage $message): bool
    {
        return $message->isFromUser() || $message->isFromGuest();
    }

    /**
     *
     */
    protected function handleUserLanguageDetection(ChatMessage $message, ChatSession $session, string $text): void
    {

        if ($message->original_language_id) {
            $this->updateSessionLanguage($session, $message->original_language_id);
            return;
        }


        $language = $this->detectLanguageCode($text);


        if ($language) {
                $message->update(['original_language_id' => $language->id]);
                $this->updateSessionLanguage($session, $language->id);
        }
    }

    /**
     *
     */
    protected function updateSessionLanguage(ChatSession $session, int $languageId): void
    {
        if ($session->active_user_language_id !== $languageId) {
            $session->update([
                'user_language_id' => $languageId,
                'active_user_language_id' => $languageId
            ]);
        }
    }

    /**
     *
     */
    protected function determineTargetLanguage(ChatMessage $message, ChatSession $session, ?int $explicitTargetId): ?int
    {
        if ($explicitTargetId) {
            return $explicitTargetId;
        }

        if ($this->isUserMessage($message)) {

            $activeAgent = $session->assignments()
                ->where('status', ChatAssignmentStatusEnum::ACTIVE)
                ->latest()
                ->first()
                ?->chatAgent;

            if ($activeAgent && $activeAgent->language_id) {
                return $activeAgent->language_id;
            }

            if ($session->agent_language_id) {
                return $session->agent_language_id;
            }

            return Language::where('code', 'en')->value('id');
        }


        if ($message->isFromAgent()) {
            return $session->active_user_language_id ?? $session->user_language_id;
        }

        return null;
    }

    /**
     *
     */
    protected function performTranslation(ChatMessage $message, string $text, int $targetLangId, ?string $requestFrom = null): void
    {
        $targetLang = Language::find($targetLangId);
        $originalLang = Language::find($message->original_language_id);

        if (!$targetLang) {
            return;
        }

        $targetCode = $targetLang->code;
        $sourceCode = $originalLang ? $originalLang->code : 'en';

        $translatedText = $this->performTranslationHelper($text, $sourceCode, $targetCode);

        if ($translatedText && $translatedText !== $text) {
            ChatMessageTranslation::updateOrCreate(
                [
                    'chat_message_id' => $message->id,
                    'target_language_id' => $targetLang->id,
                ],
                [
                    'translated_text' => $translatedText
                ]
            );

            if (!in_array($requestFrom, ['translate-session', 'translate-single'])) {
                $message->update(['message_text' => $translatedText]);
            }

            if ($requestFrom !== 'translate-session') {
                BroadcastRealtimeChat::dispatch($message);
            }
        }
    }


    private function detectLanguageCode(string $text): ?Language
    {
        if (mb_strlen(trim($text)) <= 3) {
            return null;
        }

        try {
            /** @var \App\Models\Helpers\Language|null $language */
            $language = DetectLanguageWithAI::run($text);

            return $language;
        } catch (Throwable $e) {
            Log::error($e->getMessage());
            Sentry::captureException($e);
            return null;
        }
    }


    private function performTranslationHelper(string $text, string $sourceCode, string $targetCode): ?string
    {
        try {
            if (trim($text) === '' || $sourceCode === $targetCode) {
                return $text;
            }

            $languageFrom = Language::where('code', $sourceCode)->first();
            $languageTo   = Language::where('code', $targetCode)->first();

            if (!$languageFrom || !$languageTo) {
                Log::warning('Language not found', [
                    'from' => $sourceCode,
                    'to'   => $targetCode,
                ]);
                return $text;
            }

            return Translate::run($text, $languageFrom, $languageTo);
        } catch (Throwable $e) {
            Sentry::captureException($e);
            return $text;
        }
    }
}
