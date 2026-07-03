<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 Jul 2026 14:03:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use LanguageDetection\Trainer;
use Lorisleiva\Actions\Concerns\AsAction;

class SetUpGuessLanguage
{
    use AsAction;

    public function handle(): void
    {
        $t = new Trainer();
        $t->setMaxNgrams(9000);
        $t->learn();
    }

    public function getCommandSignature(): string
    {
        return 'translations:setup-guess-language';
    }

    public function asCommand($command): void
    {
        $this->handle();
        $command->info('Language detection training completed successfully.');
    }

}
