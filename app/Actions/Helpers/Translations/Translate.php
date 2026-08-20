<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use VildanBina\LaravelAutoTranslation\TranslationWorkflowService;
use VildanBina\LaravelAutoTranslation\Services\TranslationEngineService;

class Translate extends OrgAction
{
    use AsAction;

    /**
     * @throws \Exception
     */
    public function handle(?string $text, Language $languageFrom, Language $languageTo, ?string $translationDriver = null): string
    {
        try {
            if ($text == null || $text == '' || $languageFrom->code == $languageTo->code) {
                return $text ?? '';
            }

            $cacheKey          = 'translate:'.sha1($languageFrom->code.'|'.$languageTo->code.'|'.$text);
            $cachedTranslation = Cache::get($cacheKey);
            if ($cachedTranslation !== null) {
                return $this->unescapeJsonEchoes($text, $cachedTranslation);
            }

            if (app()->environment('local') && !config('app.sandbox.translate')) {
                return $text;
            }
            $translationEngineService   = new TranslationEngineService();
            $translationWorkflowService = new TranslationWorkflowService($translationEngineService);

            $texts = [
                'text_to_translate' => $text,
            ];

            $translationWorkflowService->setInMemoryTexts($texts);

            $translatedTexts = $translationWorkflowService->translate($languageFrom->code, $languageTo->code, $translationDriver ?? config('auto-translations.default_driver'));

            $text = $this->unescapeJsonEchoes($text, Arr::get($translatedTexts, 'text_to_translate', $text));

            $cacheTtlHours = mb_strlen($text) < 32 ? 1440 : (mb_strlen($text) < 256 ? 480 : 72);
            Cache::put($cacheKey, $text, now()->addHours($cacheTtlHours));

            return $text;
        } catch (\Throwable $e) {
            Sentry::captureMessage($e->getMessage());

            return $text;
        }
    }

    /**
     * LLM translation drivers round-trip through a JSON payload and frequently echo the
     * JSON escaping back as literal characters, storing style=\\" and <\\/strong> in the text.
     */
    public function unescapeJsonEchoes(string $original, string $translated): string
    {
        if (str_contains($original, '\\') || json_validate($original)) {
            return $translated;
        }

        return self::stripJsonEscapes($translated);
    }

    public static function stripJsonEscapes(string $text): string
    {
        for ($pass = 0; $pass < 5; $pass++) {
            $stripped = preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})/',
                fn (array $m) => mb_chr(hexdec($m[1]), 'UTF-8'),
                str_replace(['\\"', '\\/', "\\'"], ['"', '/', "'"], $text)
            );

            if ($stripped === $text) {
                return $text;
            }

            $text = $stripped;
        }

        return $text;
    }

    public function getCommandSignature(): string
    {
        return 'translate {languageFrom} {languageTo} {text}';
    }


    public function rules(): array
    {
        return [
            'text' => ['required', 'string']
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(string $languageFrom, string $languageTo, ActionRequest $request): string
    {
        set_time_limit(100);

        $this->initialisationFromGroup(group(), $request);
        $languageFrom = Language::where('code', $languageFrom)->first();
        $languageTo   = Language::where('code', $languageTo)->first();
        $text         = Arr::get($this->validatedData, 'text');


        return $this->handle($text, $languageFrom, $languageTo);
    }

    /**
     * @throws \Exception
     */
    public function asCommand($command): void
    {
        $text         = $command->argument('text');
        $languageFrom = Language::where('code', $command->argument('languageFrom'))->firstOrFail();
        $languageTo   = Language::where('code', $command->argument('languageTo'))->firstOrFail();

        $translation = $this->handle($text, $languageFrom, $languageTo);
        $command->info($text.' -> '.$translation);
    }


}
