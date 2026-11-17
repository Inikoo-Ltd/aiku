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
     *
     * A family kept off the website that holds products made for customers (aw-aromatics'
     * Personalised Products, Something Different, The Yorkshire Soap Company) is where other
     * customers' own products live, and its siblings with no customer set are theirs too (HELP-4724:
     * a bespoke prospect was shown them). They are left out, except for a customer with a product of
     * their own in that family.
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
            ->visibleToCustomer($customer?->id)
            ->whereNotExists(fn ($family) => $family->from('product_categories')
                ->whereColumn('product_categories.id', 'products.family_id')
                ->where('product_categories.is_in_website', false)
                ->whereExists(fn ($made) => $made->from('products as made_for_customers')
                    ->join('product_has_exclusive_customers', 'product_has_exclusive_customers.product_id', '=', 'made_for_customers.id')
                    ->whereColumn('made_for_customers.family_id', 'product_categories.id'))
                ->when($customer, fn ($query) => $query->whereNotExists(fn ($own) => $own->from('products as own_products')
                    ->join('product_has_exclusive_customers as own_exclusive', 'own_exclusive.product_id', '=', 'own_products.id')
                    ->whereColumn('own_products.family_id', 'product_categories.id')
                    ->where('own_exclusive.customer_id', $customer->id))));
    }
}
