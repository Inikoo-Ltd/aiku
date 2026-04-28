<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Sept 2025 11:33:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Events\TranslateProgressEvent;
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
    public function handle(?string $text, Language $languageFrom, Language $languageTo, $broadcastRandomString = null): string
    {
        try {
            if ($text == null || $text == '' || $languageFrom->code == $languageTo->code) {
                return $text ?? '';
            }

            if (!config('app.sandbox.translate')) {
                return $text;
            }

            $cacheKey = 'translate:'.sha1($languageFrom->code.'|'.$languageTo->code.'|'.$text);
            $cachedTranslation = Cache::get($cacheKey);
            if ($cachedTranslation !== null) {
                if ($broadcastRandomString != null) {
                    TranslateProgressEvent::dispatch($cachedTranslation, $broadcastRandomString);
                }

                return $cachedTranslation;
            }


            $translationEngineService   = new TranslationEngineService();
            $translationWorkflowService = new TranslationWorkflowService($translationEngineService);

            $texts = [
                'text_to_translate' => $text,
            ];

            $translationWorkflowService->setInMemoryTexts($texts);

            $translatedTexts = $translationWorkflowService->translate($languageFrom->code, $languageTo->code, config('auto-translations.default_driver'));

            $text = Arr::get($translatedTexts, 'text_to_translate', $text);

            $cacheTtlHours = mb_strlen($text) < 32 ? 1440 : (mb_strlen($text) < 256 ? 480 : 72);
            Cache::put($cacheKey, $text, now()->addHours($cacheTtlHours));

            if ($broadcastRandomString != null) {
                TranslateProgressEvent::dispatch($text, $broadcastRandomString);
            }


            return $text;
        } catch (\Throwable $e) {
            Sentry::captureMessage($e->getMessage());

            return $text;
        }
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

        /* $randomString = Str::random(10);
        Translate::dispatch($text, $languageFrom, $languageTo, $randomString);

        return $randomString; */

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
