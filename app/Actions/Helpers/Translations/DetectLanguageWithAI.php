<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 11:55:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use Throwable;
use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry\Laravel\Facade as Sentry;
use Illuminate\Support\Facades\Log;

class DetectLanguageWithAI extends OrgAction
{
    use AsAction;


    /**
     * Detect the language of the given text using AI (reusing auto-translations config).
     *
     * @param  string|null  $text  The text to analyze for language detection
     * @param  Language|null  $languageHint  Optional language hint to improve detection accuracy or provide context
     *
     * @return Language|null
     */
    public function handle(?string $text, ?Language $languageHint = null): ?Language
    {
        if (empty($text) || is_numeric($text)) {
            return null;
        }

        try {
            /** @noinspection SpellCheckingInspection */
            $driverName = config('auto-translations.default_driver', 'chatgpt5');

            $driverConfig = config("auto-translations.drivers.$driverName");

            $apiKey = $driverConfig['api_key'] ?? null;

            if (empty($apiKey)) {
                Log::error("DetectLanguageWithAI: Missing API Key for driver $driverName");

                return null;
            }

            $model = 'gpt-4o-mini';

            $systemPrompt = 'You are a language detector. Reply ONLY with the ISO 639-1 language code (e.g., en, es, fr, id). If unknown, reply "null".';

            if ($languageHint) {
                $systemPrompt .= " The text is likely in $languageHint->name ($languageHint->code); only override this if you are confident the text is in a different language.";
            }

            $response = Http::withToken($apiKey)
                ->connectTimeout(10)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'       => $model,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => $systemPrompt
                        ],
                        [
                            'role'    => 'user',
                            'content' => substr($text, 0, 100)
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens'  => 5,
                ]);

            if (!$response->successful()) {
                Log::error("DetectLanguageWithAI API Error: ".$response->body());

                return null;
            }

            $rawCode = $response->json('choices.0.message.content');
            $code    = is_string($rawCode) ? trim($rawCode) : '';

            $code = strtolower(str_replace(['"', "'", '.'], '', $code));
            $code = Str::of($code)->squish()->value();

            if ($code === 'null' || empty($code)) {
                return null;
            }

            return Language::where('code', $code)->first();
        } catch (Throwable $e) {
            Sentry::captureMessage("DetectLanguageWithAI Error: ".$e->getMessage());

            return null;
        }
    }

    public function getCommandSignature(): string
    {
        return 'detect_language:ai {text : Text to detect}';
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:1000'],
        ];
    }

    public function asController(ActionRequest $request): ?Language
    {
        $this->initialisationFromGroup(group(), $request);
        $text = Arr::get($this->validatedData, 'text');

        return $this->handle($text);
    }

    public function asCommand($command): void
    {
        $text = $command->argument('text');
        $command->info("Detecting language for: \"$text\"");

        $language = $this->handle($text);

        if ($language) {
            $command->info("Detected: $language->name ($language->code)");
        } else {
            $command->error("Could not detect language.");
        }
    }
}
