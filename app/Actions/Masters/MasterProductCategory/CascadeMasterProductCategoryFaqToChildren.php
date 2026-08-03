<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Helpers\Translations\Translate;
use App\Events\MasterProductCategoryFaqCascadeProgressEvent;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cascades a master product category's FAQ to the child product categories whose shop
 * follows the master, translating it into each shop's language on the way, and
 * broadcasting progress so the edit UI can show "n/total shops updated" live.
 *
 * Always queued: each non-english child costs a translation round trip, so a master with
 * a handful of shops would otherwise hold the save request open for tens of seconds.
 */
class CascadeMasterProductCategoryFaqToChildren
{
    use AsAction;

    public string $jobQueue = 'translate';

    public function handle(MasterProductCategory $masterProductCategory): void
    {
        $english = Language::where('code', 'en')->first();

        $productCategories = $masterProductCategory->productCategories()
            ->with(['shop', 'shop.language'])
            ->get()
            ->filter(function (ProductCategory $productCategory) {
                return (bool)data_get($productCategory->shop->settings, "catalog.{$productCategory->type->value}_follow_master");
            })
            ->values();

        $total = $productCategories->count();

        MasterProductCategoryFaqCascadeProgressEvent::dispatch($masterProductCategory, [
            'state' => 'updating',
            'done'  => 0,
            'total' => $total,
        ]);

        /** @var ProductCategory $productCategory */
        foreach ($productCategories as $index => $productCategory) {
            UpdateProductCategory::make()->action($productCategory, [
                'faq' => $this->getFaqForShopLanguage($masterProductCategory, $productCategory, $english),
            ]);

            MasterProductCategoryFaqCascadeProgressEvent::dispatch($masterProductCategory, [
                'state' => 'updating',
                'done'  => $index + 1,
                'total' => $total,
            ]);
        }

        MasterProductCategoryFaqCascadeProgressEvent::dispatch($masterProductCategory, [
            'state' => 'done',
            'done'  => $total,
            'total' => $total,
        ]);
    }

    private function getFaqForShopLanguage(MasterProductCategory $masterProductCategory, ProductCategory $productCategory, ?Language $english): array
    {
        $faq = $masterProductCategory->faq ?? [];

        $shopLanguage = $productCategory->shop->language;
        if (!$english || !$shopLanguage || $shopLanguage->code == 'en') {
            return $faq;
        }

        $translatedFaq = Translate::run(json_encode($faq), $english, $shopLanguage, 'gpt-5-nano');
        if (!is_string($translatedFaq)) {
            return $faq;
        }

        $decodedFaq = json_decode($translatedFaq, true);

        return is_array($decodedFaq) ? $decodedFaq : $faq;
    }
}
