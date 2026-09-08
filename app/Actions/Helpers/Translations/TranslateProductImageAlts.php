<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sept 2026 09:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Catalogue\Product\BreakProductInWebpagesCache;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsAction;

class TranslateProductImageAlts
{
    use AsAction;

    public string $jobQueue = 'translate-model';

    public function handle(Product $product): void
    {
        $shopLanguage = $product->shop?->language;

        if (!$shopLanguage || $shopLanguage->code === 'en') {
            return;
        }

        $english = Language::where('code', 'en')->first();

        if (!$english) {
            return;
        }

        $translatedAny = false;

        foreach ($product->images as $image) {
            $sourceCaption = trim((string)$image->pivot->caption);

            if ($image->pivot->is_caption_reviewed || $image->pivot->source_caption !== null || $sourceCaption === '') {
                continue;
            }

            $translatedCaption = Translate::run($sourceCaption, $english, $shopLanguage, 'gpt-5-nano');

            if ($translatedCaption === $sourceCaption) {
                continue;
            }

            $product->images()->updateExistingPivot($image->id, [
                'caption'        => $translatedCaption,
                'source_caption' => $sourceCaption,
            ]);

            $translatedAny = true;
        }

        if ($translatedAny) {
            BreakProductInWebpagesCache::dispatch($product)->delay(15);
        }
    }
}
