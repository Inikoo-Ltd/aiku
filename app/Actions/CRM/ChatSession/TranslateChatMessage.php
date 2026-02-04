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


        $detectedLangCode = $this->detectLanguageCode($text);


        if ($detectedLangCode) {

            $language = Language::where('code', $detectedLangCode)->first();

            if ($language) {

                $message->update(['original_language_id' => $language->id]);

                $this->updateSessionLanguage($session, $language->id);
            }
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
        $sourceCode = $originalLang ? $originalLang->code : 'auto-detect';

        $translatedText = $this->performTranslationWithOpenAI($text, $sourceCode, $targetCode);

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


    private function detectLanguageCode(string $text): ?string
    {

        if (mb_strlen($text) <= 3) {
            return null;
        }

        try {
            $apiKey = env('CHATGPT_TRANSLATIONS_API_KEY');
            if (!$apiKey) {
                Sentry::captureMessage("Missing CHATGPT_TRANSLATIONS_API_KEY");
                return null;
            }

            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a language detector. Reply ONLY with the ISO 639-1 language code (e.g., en, es, fr, id, ar). Do not write anything else.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $text
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 5,
            ]);

            $code = trim($response->choices[0]->message->content);

            if (preg_match('/^[a-z]{2}$/i', $code)) {
                return strtolower($code);
            }

            return null;
        } catch (\Throwable $e) {
            Sentry::captureException($e);
            return null;
        }
    }

    private function performTranslationWithOpenAI(string $text, string $sourceCode, string $targetCode): ?string
    {
        try {
            $apiKey = env('CHATGPT_TRANSLATIONS_API_KEY');
            if (!$apiKey) {
                Sentry::captureMessage("Missing CHATGPT_TRANSLATIONS_API_KEY");
                return null;
            }

            $client = OpenAI::client($apiKey);

            $response = $client->chat()->create([
                'model' => 'gpt-5-nano',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a professional translator. Translate the text accurately. Do not add quotes or explanations. Return ONLY the translated text.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Translate from {$sourceCode} to {$targetCode}:\n\n{$text}"
                    ],
                ],
                'temperature' => 1,
            ]);

            return trim($response->choices[0]->message->content);
        } catch (\Exception $e) {
            Sentry::captureException($e);
            return null;
        }
    }
}
