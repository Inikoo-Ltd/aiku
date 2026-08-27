<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Masters\MasterProductCategory\Traits\TranslateJsonbField;
use App\Events\MasterProductCategoryJsonbCascadeProgressEvent;
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
    use TranslateJsonbField;

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

        MasterProductCategoryJsonbCascadeProgressEvent::dispatch($masterProductCategory, [
            'state' => 'updating',
            'done'  => 0,
            'total' => $total,
        ],
        'faq-cascade-progress');

        /** @var ProductCategory $productCategory */
        foreach ($productCategories as $index => $productCategory) {
            UpdateProductCategory::make()->action($productCategory, [
                'faq' => $this->getJsonbForShopLanguage($masterProductCategory, $productCategory, $english, 'faq'),
            ]);

            MasterProductCategoryJsonbCascadeProgressEvent::dispatch($masterProductCategory, [
                'state' => 'updating',
                'done'  => $index + 1,
                'total' => $total,
            ],
            'faq-cascade-progress');
        }

        MasterProductCategoryJsonbCascadeProgressEvent::dispatch($masterProductCategory, [
            'state' => 'done',
            'done'  => $total,
            'total' => $total,
        ],
        'faq-cascade-progress');
    }
}
