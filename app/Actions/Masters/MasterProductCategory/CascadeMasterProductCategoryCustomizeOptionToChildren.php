<?php

/*
 * Author Louis Perez
 * Created on 20-08-2026-10h-26m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\Helpers\Translations\Translate;
use App\Actions\Masters\MasterProductCategory\Traits\TranslateJsonbField;
use App\Events\MasterProductCategoryJsonbCascadeProgressEvent;
use App\Models\Catalogue\ProductCategory;
use App\Models\Helpers\Language;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cascades a master product category's Customize Option to the child product categories whose shop
 * follows the master, translating it into each shop's language on the way, and
 * broadcasting progress so the edit UI can show "n/total shops updated" live.
 *
 * Always queued: each non-english child costs a translation round trip, so a master with
 * a handful of shops would otherwise hold the save request open for tens of seconds.
 */
class CascadeMasterProductCategoryCustomizeOptionToChildren
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
        'customize-option-cascade-progress');

        /** @var ProductCategory $productCategory */
        foreach ($productCategories as $index => $productCategory) {
            UpdateProductCategory::make()->action($productCategory, [
                'customize_option' => $this->getJsonbForShopLanguage($masterProductCategory, $productCategory, $english, 'customize_option'),
            ]);

            MasterProductCategoryJsonbCascadeProgressEvent::dispatch($masterProductCategory, [
                'state' => 'updating',
                'done'  => $index + 1,
                'total' => $total,
            ],
            'customize-option-cascade-progress');
        }

        MasterProductCategoryJsonbCascadeProgressEvent::dispatch($masterProductCategory, [
            'state' => 'done',
            'done'  => $total,
            'total' => $total,
        ],
        'customize-option-cascade-progress');
    }
}
