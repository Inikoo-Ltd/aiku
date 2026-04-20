<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Sunday, 20 Apr 2026 16:55:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Collection;

class GetNewestProducts extends OrgAction
{
    public function handle(Shop $shop, int $limit = 5): Collection
    {
        return Product::query()
            ->where('shop_id', $shop->id)
            ->where('is_for_sale', true)
            ->where('state', ProductStateEnum::ACTIVE)
            ->with(['frontImage', 'webpage'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->frontImage?->getUrl() ?? null,
                    'link' => $product->webpage?->canonical_url ?? null,
                ];
            });
    }
}
