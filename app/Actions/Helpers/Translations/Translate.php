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
    public function handle($text, Language $language): string
    {
        if ($text == '' || $language->code == 'en') {
            return $text;
        }

        $translationEngineService = new TranslationEngineService();
        $translationWorkflowService = new TranslationWorkflowService($translationEngineService);

        $texts = [
            'text_to_translate' => $text,
        ];

        $translationWorkflowService->setInMemoryTexts($texts);

        $translatedTexts = $translationWorkflowService->translate('en', $language->code, config('auto-translations.default_driver'));

        return Arr::get($translatedTexts, 'text_to_translate', $text);
    }

    public function getCommandSignature(): string
    {
        return 'translate {language} {text}';
    }


    public function rules(): array
    {
        return [
            'text' => ['required','string']
        ];

    }

    /**
     * @throws \Exception
     */
    public function asController($language, ActionRequest $request): string
    {
        $this->initialisationFromGroup(group(), $request);
        $text = Arr::get($this->validatedData, 'text');
        return $this->handle($text, $language);
    }

    /**
     * @throws \Exception
     */
    public function asCommand($command): void
    {
        $text = $command->argument('text');
        $language = Language::where('code', $command->argument('language'))->firstOrFail();

        $translation = $this->handle($text, $language);
        $command->info($text.' -> '.$translation);

    }


}
