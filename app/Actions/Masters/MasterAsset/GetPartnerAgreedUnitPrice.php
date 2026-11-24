<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Models\Catalogue\Product;
use App\Models\Masters\MasterAssetPartnerPrice;
use App\Models\Procurement\OrgPartner;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The price per product unit (outer) the partner agreed to pay for a product, in the product's shop
 * currency at today's rate, or null when no price was agreed and the hub partner discount applies.
 */
class GetPartnerAgreedUnitPrice
{
    use AsObject;

    public function handle(Product $product, OrgPartner $orgPartner): ?float
    {
        if (!$product->master_product_id) {
            return null;
        }

        /** @var MasterAssetPartnerPrice|null $partnerPrice */
        $partnerPrice = MasterAssetPartnerPrice::where('master_asset_id', $product->master_product_id)
            ->where('org_partner_id', $orgPartner->id)
            ->with('currency')
            ->first();

        if (!$partnerPrice) {
            return null;
        }

        $shopCurrency = $product->shop->currency;
        if ($partnerPrice->currency_id === $shopCurrency->id) {
            return round((float) $partnerPrice->price, 2);
        }

        $exchange = GetCurrencyExchange::run($partnerPrice->currency, $shopCurrency);

        return round((float) $partnerPrice->price * (float) $exchange, 2);
    }
}
