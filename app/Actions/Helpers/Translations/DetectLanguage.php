<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 12:07:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\OrgAction;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsAction;

class DetectLanguage extends OrgAction
{
    use AsAction;


    public function handle($text, ?Language $languageHint = null): ?Language
    {
        if ($text == '' || is_numeric($text)) {
            return null;
        }

        $guess = GuessLanguage::run($text, null, 0.8);
        if ($guess) {
            return $guess;
        }

        return DetectLanguageWithAI::run($text, $languageHint);
    }


    public function getCommandSignature(): string
    {
        return 'detect-language {text} {--hint= : Language code hint}';
    }

    public function asCommand($command): void
    {
        $text = $command->argument('text');

        $hintLanguage = null;
        if ($command->option('hint')) {
            $hintLanguage = Language::where('code', $command->option('hint'))->firstOrFail();
        }


        $translation = $this->handle($text, $hintLanguage);
        $command->info($text.' -> '.($translation?->code ?? 'unknown'));
    }


}
