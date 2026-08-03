<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:57:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use LanguageDetection\Language as LanguageDetection;

class GuessLanguage extends OrgAction
{
    use AsAction;

    /**
     * @throws \Exception
     */
    public function handle($text, ?Language $languageHint = null, $umbral = 0.9, array $haystack = []): ?Language
    {
        if ($text == '' || is_numeric($text)) {
            return null;
        }


        if (empty($haystack)) {
            $languageDetection = new LanguageDetection();
        } else {
            $languageDetection = new LanguageDetection($haystack);
        }


        $languageDetection->setMaxNgrams(9000);

        $result = $languageDetection->detect($text)->bestResults()->close();

        $languageCode = array_key_first($result);


        if (!$languageCode || $result[$languageCode] <= $umbral) {
            return $languageHint;
        }


        return Language::where('code', $languageCode)->first();
    }

    public function getCommandSignature(): string
    {
        return 'guess_language {text : Text to detect} {--haystack=* : Language codes to restrict detection (repeat option or comma-separated)} {--umbral=0 : Confidence threshold (0..1) to consider detection valid}';
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
        $umbral   = (float)Arr::get($this->validatedData, 'umbral', 0);

        return $this->handle($text, null, $umbral, $haystack);
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

        $umbral = (float)($command->option('umbral') ?? 0);

        $translation = $this->handle($text, null, $umbral, $haystack);
        $command->info($text.' -> '.($translation?->code ?? 'unknown'));
    }


}
