<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Sept 2025 11:07:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LanguageDetection\Language as LanguageDetection;

class DetectLanguage extends OrgAction
{
    use AsAction;

    /**
     * @throws \Exception
     */
    public function handle($text, array $haystack = [], $umbral = 0): ?Language
    {
        if ($text == '' || is_numeric($text)) {
            return null;
        }

        if (empty($haystack)) {
            $languageDetection = new LanguageDetection();
        } else {
            $languageDetection = new LanguageDetection($haystack);
        }
        $result = $languageDetection->detect($text)->bestResults()->close();

        $languageCode = array_key_first($result);




        if (!$languageCode || $result[$languageCode] <= $umbral) {
            return null;
        }


        return Language::where('code', $languageCode)->first();
    }

    public function getCommandSignature(): string
    {
        return 'detect_language {text : Text to detect} {--haystack=* : Language codes to restrict detection (repeat option or comma-separated)} {--umbral=0 : Confidence threshold (0..1) to consider detection valid}';
    }


    public function rules(): array
    {
        return [
            'text'     => ['required', 'string'],
            'haystack' => ['sometimes', 'array'],
            'umbral'   => ['sometimes', 'numeric']
        ];
    }

    /**
     * @throws \Exception
     */
    public function asController(ActionRequest $request): string
    {
        $this->initialisationFromGroup(group(), $request);
        $text     = Arr::get($this->validatedData, 'text');
        $haystack = Arr::get($this->validatedData, 'haystack', []);
        $umbral   = (float) Arr::get($this->validatedData, 'umbral', 0);

        return $this->handle($text, $haystack, $umbral);
    }

    /**
     * @throws \Exception
     */
    public function asCommand($command): void
    {
        $text = $command->argument('text');

        $haystack = $command->option('haystack') ?? [];
        if (is_string($haystack)) {
            $haystack = [$haystack];
        }
        if (is_array($haystack) && count($haystack) === 1 && is_string($haystack[0]) && str_contains($haystack[0], ',')) {
            $haystack = array_filter(array_map('trim', explode(',', $haystack[0])));
        } else {
            $haystack = array_values(array_filter(array_map('trim', (array)$haystack)));
        }

        $umbral = (float) ($command->option('umbral') ?? 0);

        $translation = $this->handle($text, $haystack, $umbral);
        $command->info($text.' -> '.($translation?->code ?? 'unknown'));
    }


}
