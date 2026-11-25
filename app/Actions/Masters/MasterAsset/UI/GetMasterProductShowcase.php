<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Masters\Competitor\UI\IndexMasterAssetCompetitorProducts;
use App\Actions\SupplyChain\SupplierProduct\UI\GetSupplierProductsShowcaseData;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\Json\GetPriceRebelProducts;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Models\Helpers\Currency;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Actions\Traits\HasBucketImages;
use App\Actions\Goods\TradeUnit\GetLabelInfoLanguages;
use App\Enums\Goods\TradeUnit\TradeUnitLabelPresenceEnum;
use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
use App\Enums\Goods\TradeUnit\TradeUnitMarketEnum;
use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use App\Http\Resources\Masters\MasterProductResource;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketAttachment;
use App\Helpers\NaturalLanguage;
use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;

class GetMasterProductShowcase
{
    use AsObject;
    use HasBucketImages;
    use HasBucketAttachment;

    public function handle(MasterAsset $masterAsset): array
    {
        $tradeUnits = $masterAsset->tradeUnits;
        $tradeUnits->loadMissing(['ingredients']);

        $countriesOrigin = [];
        $countries      = array_filter(array_map('trim', explode(',', $masterAsset->country_of_origin ?? '')));
        foreach ($countries as $country) {
            $countriesOrigin[] = NaturalLanguage::make()->country($country);
        }

        $properties = [
            'countries_of_origin' => $countriesOrigin,
            'ingredients'       => $masterAsset->marketing_ingredients ? explode(', ', $masterAsset->marketing_ingredients) : [],
            'tariff_code'       => $masterAsset->tariff_code,
            'duty_rate'         => $masterAsset->duty_rate,
            'hts_us'            => $masterAsset->hts_us,
        ];

        $gpsr = [
            'manufacturer'               => $masterAsset->gpsr_manufacturer,
            'eu_responsible'             => $masterAsset->gpsr_eu_responsible,
            'warnings'                   => $masterAsset->gpsr_warnings,
            'how_to_use'                 => $masterAsset->gpsr_manual,
            'gpsr_class_category_danger' => $masterAsset->gpsr_class_category_danger,
            'product_languages'          => $masterAsset->gpsr_class_languages,
            'acute_toxicity'             => $masterAsset->pictogram_toxic,
            'corrosive'                  => $masterAsset->pictogram_corrosive,
            'explosive'                  => $masterAsset->pictogram_explosive,
            'flammable'                  => $masterAsset->pictogram_flammable,
            'gas_under_pressure'         => $masterAsset->pictogram_gas,
            'hazard_environment'         => $masterAsset->pictogram_environment,
            'health_hazard'              => $masterAsset->pictogram_health,
            'oxidising'                  => $masterAsset->pictogram_oxidising,
        ];


        $dataTradeUnits = [];
        if ($masterAsset->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($masterAsset);
        }

        $product = $masterAsset
            ->products()
            ->select('products.id', 'products.slug', 'products.code', 'products.shop_id', 'products.organisation_id', 'products.is_for_sale')
            ->with(['shop:id,code,slug,state'])
            ->with(['organisation:id,code,slug'])
            ->get()
            ->filter(fn ($item) => $item->shop->state->value !== 'closed');

        $parentLink = null;
        if ($masterAsset->not_for_sale_from_trade_unit && request()->user()?->authTo(['goods.edit', 'compliance.edit'])) {
            $parentLink = [
                'url'    => "grp.trade_units.units.edit",
                'params' => [
                    'tradeUnit' => $masterAsset->tradeUnits->where('is_for_sale', false)->first()->slug,
                ]
            ];
        }

        return [
            'images'              => $this->getImagesData($masterAsset),
            'main_image'          => $masterAsset->imageSources(),
            'majorCurrencies'     => collect($masterAsset->masterShop?->price_exchanges ?? [])
                ->filter(fn (array $exchangeData) => $exchangeData['is_major'] ?? false)
                ->keys()
                ->values(),
            'pricingCosts'        => $this->getPricingCosts($masterAsset),
            'partnerPricing'      => $this->getPartnerPricing($masterAsset),
            'materialCost'        => GetMasterAssetMaterialCost::run($masterAsset),
            'competitors'         => IndexMasterAssetCompetitorProducts::make()->summary($masterAsset),
            'is_dropship'         => $masterAsset->masterShop?->type == ShopTypeEnum::DROPSHIPPING,
            'masterProduct'       => MasterProductResource::make($masterAsset)->resolve(),
            'properties'          => $properties,
            'trade_units'         => $dataTradeUnits,
            'supplier_products'   => GetSupplierProductsShowcaseData::run($masterAsset->tradeUnits->load('supplierProducts')->flatMap->supplierProducts->unique('id')),
            'rebel_prices'        => GetPriceRebelProducts::run($masterAsset, ['type' => 'price']),
            'rebel_rrp'           => GetPriceRebelProducts::run($masterAsset, ['type' => 'rrp']),
            'gpsr'                => $gpsr,
            'label_info'          => [
                ...TradeUnitLabelPresenceEnum::presenceFromLabelInfo($masterAsset->label_info),
                'label_info_approved' => ['show' => data_get($masterAsset->label_info, 'label_info_approved', false) === true],
                'markets'   => TradeUnitMarketEnum::marketsFromLabelInfo($masterAsset->label_info),
                'languages' => GetLabelInfoLanguages::run($masterAsset->label_info),
                'best_before' => TradeUnitBestBeforeEnum::bestBeforeFromLabelInfo($masterAsset->label_info),
                'packaging_material_codes' => TradeUnitPackagingMaterialEnum::packagingMaterialCodesFromLabelInfo($masterAsset->label_info),
            ],
            'attachment_box'      => [
                'public'  => [],
                'private' => []
            ],
            'availability_status' => [
                'is_for_sale'            => $masterAsset->is_for_sale,
                'product'                => $product->toArray(),
                'status'                 => $masterAsset->status,
                'total_products'         => $masterAsset->stats->number_assets,
                'total_product_for_sale' => $masterAsset->stats->number_current_assets,
                'from_trade_unit'        => $masterAsset->not_for_sale_from_trade_unit,
                'parentLink'             => $parentLink,
            ],
        ];
    }

    /**
     * Effective cost (group currency, per outer) converted into every shop currency,
     * so the UI can show margin vs cost next to each price — same maths as the
     * pricing tab and the master product edit blueprint.
     *
     * @return array<string, float|null>|null
     */
    public function getPricingCosts(MasterAsset $masterAsset): ?array
    {
        if ($masterAsset->effective_cost === null || !$masterAsset->masterShop) {
            return null;
        }

        $groupCurrency = $masterAsset->group->currency;

        return GetMasterShopCurrenciesRate::run($masterAsset->masterShop)
            ->map(function ($rate, string $currencyCode) use ($masterAsset, $groupCurrency) {
                $currency = Currency::where('code', $currencyCode)->first();
                $exchange = $currency ? GetCurrencyExchange::run($groupCurrency, $currency) : null;

                return $exchange ? round((float) $masterAsset->effective_cost * $exchange, 2) : null;
            })
            ->all();
    }

    /**
     * Aromatics only: what each partner pays for one outer (its custom price, or the list price less
     * the hub partner discount, in the partner's currency) and the seller's margin over the effective cost.
     * Same maths as the Partners pay column of the pricing tab.
     *
     * @return array{partner_discount: float, partners: list<array{code: string, currency_code: string, price: float, margin: int|null, is_custom: bool}>}|null
     */
    public function getPartnerPricing(MasterAsset $masterAsset): ?array
    {
        if (!GetMasterAssetsPartnerPricing::appliesTo($masterAsset->masterShop)) {
            return null;
        }

        $pricing = GetMasterAssetsPartnerPricing::run($masterAsset->masterShop, collect([$masterAsset]));
        $row     = $pricing['rows'][$masterAsset->id] ?? null;
        if (!$row) {
            return null;
        }

        $partnerDiscount = (float) $pricing['meta']['partner_discount'];

        return [
            'partner_discount' => $partnerDiscount,
            'sibling'          => $this->getSiblingPricing($row, $pricing['meta'], $partnerDiscount),
            'partners'         => collect($pricing['meta']['partners'])->map(function (array $partner) use ($row, $partnerDiscount) {
                $customPrice = $row['prices'][$partner['org_partner_id']] ?? null;
                $fromBase    = (float) $partner['from_base_rate'] ?: 1.0;
                $price       = $customPrice ?? round($row['list_price'] * (1 - $partnerDiscount) * $fromBase, 2);
                $paidInBase  = $price / $fromBase;

                return [
                    'code'          => $partner['code'],
                    'currency_code' => $partner['currency_code'],
                    'price'         => $price,
                    'margin'        => $row['cost'] !== null && $paidInBase > 0 ? (int) round(($paidInBase - $row['cost']) / $paidInBase * 100) : null,
                    'is_custom'     => $customPrice !== null,
                ];
            })->values()->all(),
        ];
    }

    /**
     * The Aromatics cost and partner cards of a master, for pages outside the master (the Aroma shop's product).
     *
     * @return array<string, mixed>|null
     */
    public function aromaPricingCards(MasterAsset $masterAsset): ?array
    {
        if (!GetMasterAssetsPartnerPricing::appliesTo($masterAsset->masterShop)) {
            return null;
        }

        return [
            'majorCurrencies' => collect($masterAsset->masterShop->price_exchanges ?? [])
                ->filter(fn (array $exchangeData) => $exchangeData['is_major'] ?? false)
                ->keys()
                ->values()
                ->all(),
            'pricingCosts'    => $this->getPricingCosts($masterAsset),
            'partnerPricing'  => $this->getPartnerPricing($masterAsset),
            'materialCost'    => GetMasterAssetMaterialCost::run($masterAsset),
            'competitors'     => IndexMasterAssetCompetitorProducts::make()->summary($masterAsset),
        ];
    }

    /**
     * What the partners sell the same code at in their own shops, per main currency of that master: price per
     * outer with the partner's margin over what it pays the seller, and RRP per unit with the margin over that
     * price. Same maths as the Partners sell at / Partners RRP columns of the pricing tab.
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $meta
     *
     * @return array{code: string, master_shop: string, currencies: list<array{currency_code: string, is_major: bool, price: float|null, price_margin: int|null, rrp: float|null, rrp_margin: int|null}>}|null
     */
    private function getSiblingPricing(array $row, array $meta, float $partnerDiscount): ?array
    {
        $sibling = $row['sibling'] ?? null;
        if (!$sibling) {
            return null;
        }

        $units = (float) ($sibling['units'] ?? 0) ?: 1.0;

        $siblingCurrencies = collect($sibling['currencies'] ?? []);

        $currencies = $siblingCurrencies
            ->keys()
            ->sortBy(fn (string $currencyCode) => data_get($siblingCurrencies->get($currencyCode), 'is_major') ? 0 : 1)
            ->map(function (string $currencyCode) use ($sibling, $siblingCurrencies, $row, $meta, $partnerDiscount, $units) {
                $price    = data_get($sibling, "master_prices.$currencyCode.value");
                $price    = $price !== null ? (float) $price : null;
                $rrpOuter = data_get($sibling, "master_rrps.$currencyCode.value");
                $rrpOuter = $rrpOuter !== null ? (float) $rrpOuter : null;
                $fromBase = $meta['rates_from_base'][$currencyCode] ?? null;

                $paidByPartners = collect($meta['partners'])
                    ->filter(fn (array $partner) => $fromBase && in_array($currencyCode, $partner['shop_currencies'] ?? [], true))
                    ->map(function (array $partner) use ($row, $partnerDiscount, $fromBase) {
                        $partnerRate = (float) $partner['from_base_rate'] ?: 1.0;
                        $paid        = $row['prices'][$partner['org_partner_id']] ?? round($row['list_price'] * (1 - $partnerDiscount) * $partnerRate, 2);

                        return $paid / $partnerRate * $fromBase;
                    });
                $partnerCost = $paidByPartners->isNotEmpty() ? $paidByPartners->max() : ($sibling['costs'][$currencyCode] ?? null);

                return [
                    'currency_code' => $currencyCode,
                    'is_major'      => (bool) data_get($siblingCurrencies->get($currencyCode), 'is_major'),
                    'price'         => $price,
                    'price_margin'  => $price && $partnerCost !== null ? (int) round(($price - $partnerCost) / $price * 100) : null,
                    'rrp'           => $rrpOuter !== null ? round($rrpOuter / $units, 2) : null,
                    'rrp_margin'    => $price && $rrpOuter ? (int) round(($rrpOuter - $price) / $rrpOuter * 100) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'code'        => $sibling['code'],
            'master_shop' => $sibling['master_shop'],
            'currencies'  => $currencies,
        ];
    }

    private function getDataTradeUnit(MasterAsset $masterAsset): array
    {
        $packedIn      = $masterAsset->getEffectiveStockPackedInByTradeUnit();
        $packedInByOrg = $masterAsset->getStockPackedInByOrganisationAndTradeUnit();

        return $masterAsset->tradeUnits->map(function (TradeUnit $tradeUnit) use ($packedIn, $packedInByOrg) {
            $packedInQuantity = Arr::get($packedIn, $tradeUnit->id, 1);

            return array_merge(
                [
                    'code'             => $tradeUnit->code,
                    'quantity'         => (float) $tradeUnit->pivot->quantity,
                    'packed_in'        => $packedInQuantity,
                    'packed_in_by_org' => ($packedInByOrg->get($tradeUnit->id) ?? collect())->values()->all(),
                    'pick_fractional'  => riseDivisor(divideWithRemainder(findSmallestFactors($tradeUnit->pivot->quantity / $packedInQuantity)), $packedInQuantity),
                ],
                GetTradeUnitShowcase::run($tradeUnit)
            );
        })->toArray();
    }


}
