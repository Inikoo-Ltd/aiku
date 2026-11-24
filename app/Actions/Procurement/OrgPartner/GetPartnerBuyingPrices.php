<?php

namespace App\Actions\Procurement\OrgPartner;

use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAssetPartnerPrice;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the partner pays for each product, per product unit in the seller's currency: the price agreed
 * for the product's master when there is one, otherwise the product price times the partner's buying
 * factor. The product-by-product counterpart of PartnerSkoPrice::buyerPricePerSkoSql.
 */
class GetPartnerBuyingPrices
{
    use AsObject;

    /**
     * @param  iterable<Product|object{id: int, price: mixed, master_product_id: int|null}>  $products
     *
     * @return array<int, array{price: float, agreed: bool}> keyed by product id
     */
    public function handle(OrgPartner $orgPartner, iterable $products): array
    {
        $products = Collection::make($products);
        $factor   = GetPartnerBuyingPriceFactor::run($orgPartner);
        $exchange = $orgPartner->exchangeToOrgCurrency();

        $agreed = MasterAssetPartnerPrice::where('org_partner_id', $orgPartner->id)
            ->whereIn('master_asset_id', $products->pluck('master_product_id')->filter()->unique()->values())
            ->pluck('price', 'master_asset_id');

        return $products->mapWithKeys(function ($product) use ($agreed, $factor, $exchange) {
            $agreedPrice = $product->master_product_id ? $agreed->get($product->master_product_id) : null;

            return [
                $product->id => $agreedPrice !== null
                    ? ['price' => (float) $agreedPrice / ($exchange > 0 ? $exchange : 1), 'agreed' => true]
                    : ['price' => (float) $product->price * $factor, 'agreed' => false],
            ];
        })->all();
    }
}
