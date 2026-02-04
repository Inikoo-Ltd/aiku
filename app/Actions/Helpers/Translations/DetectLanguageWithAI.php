<?php

namespace App\Actions\Helpers\Translations;

use Throwable;
use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
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
     * @param string|null $text
     * @return Language|null
     */
    public function handle(?string $text): ?Language
    {
        if (empty($text) || is_numeric($text)) {
            return null;
        }

        try {
            $driverName = config('auto-translations.default_driver', 'chatgpt5');

            $driverConfig = config("auto-translations.drivers.{$driverName}");

            $apiKey = $driverConfig['api_key'] ?? null;

            if (empty($apiKey)) {
                Log::error("DetectLanguageWithAI: Missing API Key for driver {$driverName}");
                return null;
            }

            $model = 'gpt-4o-mini';

            $response = Http::withToken($apiKey)
                ->timeout(5)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a language detector. Reply ONLY with the ISO 639-1 language code (e.g., en, es, fr, id). If unknown, reply "null".'
                        ],
                        [
                            'role' => 'user',
                            'content' => substr($text, 0, 500)
                        ],
                    ],
                    'temperature' => 0,
                    'max_tokens' => 5,
                ]);

            if (!$response->successful()) {
                Sentry::captureMessage("DetectLanguageWithAI API Error: " . $response->body());
                return null;
            }

            $code = trim($response->json('choices.0.message.content'));

            $code = strtolower(str_replace(['"', "'", '.'], '', $code));

            if ($code === 'null' || empty($code)) {
                return null;
            }

            return Language::where('code', $code)->first();
        } catch (Throwable $e) {
            Sentry::captureMessage("DetectLanguageWithAI Error: " . $e->getMessage());
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
        $command->info("Detecting language for: \"{$text}\"");

        $language = $this->handle($text);

        if ($language) {
            $command->info("Detected: {$language->name} ({$language->code})");
        } else {
            $command->error("Could not detect language.");
        }
    }
}
