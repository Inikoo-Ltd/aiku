<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sept 2026 09:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Helpers\Translations\TranslateProductImageAlts;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairProductImageAltTranslations
{
    use AsAction;

    public string $commandSignature = 'repair:product_image_alt_translations {--shop= : shop slug}';

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shopsIds = Shop::whereHas('language', fn ($q) => $q->where('code', '!=', 'en'))->pluck('id')->toArray();

        if ($command->option('shop')) {
            $shop = Shop::where('slug', $command->option('shop'))->firstOrFail();

            if (!in_array($shop->id, $shopsIds)) {
                $command->info("Shop $shop->slug is in English, nothing to translate");

                return 0;
            }

            $shopsIds = [$shop->id];
        }

        $query = Product::whereIn('shop_id', $shopsIds)
            ->whereHas('images', fn ($q) => $q->whereNull('model_has_media.source_caption')
                ->where('model_has_media.is_caption_reviewed', false)
                ->whereNotNull('model_has_media.caption')
                ->where('model_has_media.caption', '!=', ''));

        $command->getOutput()->progressStart($query->count());

        $query->orderBy('id')->chunkById(100, function (Collection $products) use ($command) {
            foreach ($products as $product) {
                TranslateProductImageAlts::dispatch($product);
                $command->getOutput()->progressAdvance();
            }
        });

        $command->getOutput()->progressFinish();

        return 0;
    }
}
