<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\RangeAdvisor;

use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use Illuminate\Database\Eloquent\Builder;

trait WithRecommendableProducts
{
    /**
     * Products of the shop that can be recommended: active, on a live webpage, in stock, priced,
     * shown on the website (a standalone product for sale or a variant leader) and visible to the customer.
     * Same rule as GenerateCustomerProductSuggestions::forSaleTo. With $inStock false the stock rule is
     * left out, to tell a customer that a product they asked for exists but is out of stock.
     */
    protected function recommendableProducts(Shop $shop, ?Customer $customer, bool $inStock = true): Builder
    {
        return Product::query()
            ->where('products.shop_id', $shop->id)
            ->where('products.state', ProductStateEnum::ACTIVE->value)
            ->where('products.has_live_webpage', true)
            ->when($inStock, fn (Builder $query) => $query->where('products.available_quantity', '>', 0))
            ->where('products.price', '>', 0)
            ->where(fn ($query) => $query->where(fn ($subQuery) => $subQuery->where('products.is_minion_variant', false)->where('products.is_for_sale', true))->orWhere('products.is_variant_leader', true))
            ->visibleToCustomer($customer?->id);
    }
}
