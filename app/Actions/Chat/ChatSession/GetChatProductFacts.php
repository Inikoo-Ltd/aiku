<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 03:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Catalogue\Product\GetProductIncomingStock;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Product\ProductStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Whether the products a customer names by code can be bought now, told the way the product
 * page tells it. When more is on order that is said, never a date: most expected dates are a
 * guess from the delivery's state, and a guessed date that passes is a promise broken.
 */
class GetChatProductFacts
{
    use AsAction;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Shop $shop, string $text): array
    {
        preg_match_all('/\b[A-Za-z][A-Za-z0-9]{1,15}-\d{1,4}[A-Za-z]?\b/u', $text, $matches);

        $codes = collect($matches[0])->map(fn (string $code) => mb_strtolower($code))->unique()->take(10)->values()->all();

        if (!$codes) {
            return [];
        }

        return Product::where('shop_id', $shop->id)
            ->whereIn(DB::raw('lower(code)'), $codes)
            ->get()
            ->map(fn (Product $product) => $this->product($product))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function product(Product $product): array
    {
        $availability = match (true) {
            $product->state === ProductStateEnum::DISCONTINUED    => 'discontinued, will not come back',
            !$product->is_for_sale                                => 'not for sale',
            $product->status === ProductStatusEnum::COMING_SOON   => 'coming soon, not in stock yet',
            $product->status === ProductStatusEnum::OUT_OF_STOCK || $product->available_quantity < 1 => 'out of stock',
            default                                               => 'in stock',
        };

        $inStock = $availability === 'in stock';

        return array_filter([
            'code'            => $product->code,
            'name'            => $product->name,
            'availability'    => $availability,
            'available_now'   => $inStock ? (int) $product->available_quantity : null,
            'sold_in_packs_of' => (float) $product->units > 1 ? (float) $product->units.' '.($product->unit ?: 'units').', so available_now counts packs, not pieces' : null,
            'last_ones'       => $inStock && $product->state === ProductStateEnum::DISCONTINUING ? 'being discontinued, only what is left' : null,
            'more_on_order'   => !$inStock && $product->state !== ProductStateEnum::DISCONTINUED && GetProductIncomingStock::make()->earliestEta($product) !== null
                ? 'yes, no confirmed date'
                : null,
        ], fn ($value) => $value !== null);
    }
}
