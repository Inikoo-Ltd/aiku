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
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use VildanBina\LaravelAutoTranslation\TranslationWorkflowService;
use VildanBina\LaravelAutoTranslation\Services\TranslationEngineService;

class Translate extends OrgAction
{
    use AsAction;

    /**
     * @throws \Exception
     */
    public function handle(?string $text, Language $languageFrom, Language $languageTo): string
    {
        if ($text == null || $text == '' || $languageFrom->code == $languageTo->code) {
            return $text ?? '';
        }

        $translationEngineService   = new TranslationEngineService();
        $translationWorkflowService = new TranslationWorkflowService($translationEngineService);

        $texts = [
            'text_to_translate' => $text,
        ];

        $translationWorkflowService->setInMemoryTexts($texts);

        $translatedTexts = $translationWorkflowService->translate($languageFrom->code, $languageTo->code, config('auto-translations.default_driver'));

        return Arr::get($translatedTexts, 'text_to_translate', $text);
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
