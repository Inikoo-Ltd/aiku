<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Variant;

use App\Actions\Helpers\Translations\Translate;
use App\Models\Catalogue\Variant;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateVariantLabel
{
    use AsAction;

    public string $jobQueue = 'translate-model';

    public function handle(Variant $variant, ?string $label, bool $overwrite = false): Variant
    {
        if ($variant->is_label_reviewed && !$overwrite) {
            return $variant;
        }

        $english = Language::where('code', 'en')->first();

        $variant->update([
            'label' => blank($label) ? null : Translate::run($label, $english, $variant->shop->language, 'gpt-5-nano'),
        ]);

        return $variant;
    }
}
