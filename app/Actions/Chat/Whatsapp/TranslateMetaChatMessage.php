<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Helpers\Translations\DetectLanguageWithAI;
use App\Actions\Helpers\Translations\Translate;
use App\Events\BroadcastRealtimeMetaChat;
use App\Models\Chat\MetaChatMessage;
use App\Models\Chat\MetaChatMessageTranslation;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Laravel\Facade as Sentry;
use Throwable;

/**
 * The WhatsApp counterpart of TranslateChatMessage. The two cannot share one action: the
 * messages live in separate tables, and a translation keyed by a plain id would otherwise
 * land on whichever row of the other table happens to carry the same number.
 */
class TranslateMetaChatMessage
{
    use AsAction;

    public int $jobTimeout = 300;

    public int $jobTries = 1;

    public string $jobQueue = 'translate-chat';

    public function handle(int $messageId, int $targetLanguageId): void
    {
        $message = MetaChatMessage::find($messageId);

        if (!$message) {
            Log::warning('Meta chat message not found', ['id' => $messageId]);

            return;
        }

        $text = trim((string) ($message->original_text ?? $message->message_text ?? ''));

        if ($text === '') {
            return;
        }

        $sourceLanguage = $message->originalLanguage ?? $this->detectLanguage($message, $text);
        $targetLanguage = Language::find($targetLanguageId);

        if (!$targetLanguage || $sourceLanguage?->id === $targetLanguage->id) {
            return;
        }

        $translated = $this->translate($text, $sourceLanguage?->code, $targetLanguage->code);

        if (!$translated || $translated === $text) {
            return;
        }

        MetaChatMessageTranslation::updateOrCreate(
            [
                'meta_chat_message_id' => $message->id,
                'target_language_id'   => $targetLanguage->id,
            ],
            ['translated_text' => $translated]
        );

        BroadcastRealtimeMetaChat::dispatch($message->fresh(['translations.targetLanguage', 'attachment', 'metaChatSession']));
    }

    /**
     * The original wording has to survive: a customer writing in Indonesian should still
     * read back what they sent, so the text is kept before anything is translated.
     */
    protected function detectLanguage(MetaChatMessage $message, string $text): ?Language
    {
        if (mb_strlen($text) <= 3) {
            return null;
        }

        try {
            $language = DetectLanguageWithAI::run($text);
        } catch (Throwable $exception) {
            Log::error($exception->getMessage());
            Sentry::captureException($exception);

            return null;
        }

        if ($language) {
            $message->updateQuietly([
                'original_language_id' => $language->id,
                'original_text'        => $message->original_text ?: $text,
            ]);
        }

        return $language;
    }

    protected function translate(string $text, ?string $sourceCode, string $targetCode): ?string
    {
        $sourceCode = strtolower(trim((string) ($sourceCode ?: 'en')));
        $targetCode = strtolower(trim($targetCode));

        if ($sourceCode === $targetCode || $targetCode === '') {
            return null;
        }

        $from = Language::where('code', $sourceCode)->first();
        $to   = Language::where('code', $targetCode)->first();

        if (!$from || !$to) {
            Log::warning('Language not found for WhatsApp translation', ['from' => $sourceCode, 'to' => $targetCode]);

            return null;
        }

        try {
            return Translate::run($text, $from, $to);
        } catch (Throwable $exception) {
            Sentry::captureException($exception);

            return null;
        }
    }
}
