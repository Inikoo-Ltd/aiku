<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 10:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\Bundle;
use App\Models\Dropshipping\BundleItem;
use App\Models\Helpers\Media;

/**
 * The copy is a new bundle with its own product, sku and unlinked portfolio, so its products can
 * change before it is published, while the original keeps describing what was listed and sold.
 */
class DuplicateBundle extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Bundle $bundle): Bundle
    {
        /** @var Product $product */
        $product = $bundle->bundleable;

        $copy = StoreBundle::make()->action($bundle->customerSalesChannel, [
            'name'        => $product->name,
            'description' => $product->description,
            'rrp'         => $product->rrp,
            'products'    => $bundle->items()
                ->where('item_type', class_basename(Product::class))
                ->get()
                ->map(fn (BundleItem $item) => ['product_id' => $item->item_id, 'quantity' => $item->quantity])
                ->all(),
        ]);

        $images = $product->images()->wherePivot('scope', 'image')->get();
        if ($images->isNotEmpty()) {
            UpdateBundle::make()->action($copy, [
                'images' => $images->map(fn (Media $media) => ['id' => $media->id, 'is_main' => $media->id === $product->image_id])->all(),
            ]);
        }

        return $copy->refresh();
    }

    /**
     * @throws \Throwable
     */
    public function action(Bundle $bundle): Bundle
    {
        $this->asAction = true;
        $this->initialisationFromShop($bundle->customer->shop, []);

        return $this->handle($bundle);
    }
}
