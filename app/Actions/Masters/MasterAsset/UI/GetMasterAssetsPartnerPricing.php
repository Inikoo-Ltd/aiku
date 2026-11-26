<?php

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Actions\Procurement\OrgPartner\GetPartnerBuyingPriceFactor;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterAssetPartnerPrice;
use App\Models\Masters\MasterShop;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the partners of the organisation selling an Aromatics master product pay for it, and the
 * AW-class master the partners' own B2B shops sell under the same code, for a batch of master
 * products of one master shop.
 *
 * @phpstan-type PartnerPricingRow array{list_price: float, cost: float|null, prices: array<int, float|null>, sibling: array<string, mixed>|null}
 */
class GetMasterAssetsPartnerPricing
{
    use AsObject;

    public const string AROMA_MASTER_SHOP_SLUG = 'aroma';

    /**
     * INI-567, 26 Nov 2026: rough Aromatics overhead per unit made, in group currency (about £1.24M a year over about
     * 1.5M units: staff time not booked on products, building, energy, ads and software). Shown as a hint next to the
     * cost, never added to effective_cost. Replace with real figures once Aroma's main bank account is connected.
     */
    public const float ESTIMATED_OVERHEAD_PER_UNIT = 0.81;

    public static function estimatedOverhead(MasterAsset $masterAsset): float
    {
        return round(self::ESTIMATED_OVERHEAD_PER_UNIT * ((float) $masterAsset->units ?: 1), 4);
    }

    public static function appliesTo(?MasterShop $masterShop): bool
    {
        return $masterShop?->slug === self::AROMA_MASTER_SHOP_SLUG;
    }

    /**
     * @return Collection<int, OrgPartner> the partners buying from the organisations that sell this master shop
     */
    public static function partners(MasterShop $masterShop): Collection
    {
        $sellingOrganisationIds = Shop::where('master_shop_id', $masterShop->id)->distinct()->pluck('organisation_id');

        return OrgPartner::whereIn('partner_id', $sellingOrganisationIds)
            ->whereNotIn('organisation_id', $sellingOrganisationIds)
            ->where('status', true)
            ->with(['organisation.currency', 'partner'])
            ->orderBy('organisation_id')
            ->get();
    }

    /**
     * @param Collection<int, MasterAsset> $masterAssets
     *
     * @return array{meta: array<string, mixed>, rows: array<int, PartnerPricingRow>}|null
     */
    public function handle(MasterShop $masterShop, Collection $masterAssets): ?array
    {
        if (!self::appliesTo($masterShop)) {
            return null;
        }

        $partners = self::partners($masterShop);
        if ($partners->isEmpty()) {
            return null;
        }

        $baseCurrencyCode = GetMasterShopCurrenciesRate::baseCurrencyCode($masterShop->price_exchanges ?? [])
            ?? $masterShop->group->currency->code;
        $baseCurrency     = Currency::where('code', $baseCurrencyCode)->firstOrFail();
        $groupCurrency    = $masterShop->group->currency;
        $costExchange     = $this->exchange($groupCurrency, $baseCurrency);

        /** @var Organisation $seller */
        $seller          = $partners->first()->partner;
        $partnerDiscount = GetPartnerBuyingPriceFactor::hubPartnerDiscount($seller);

        $customPrices = MasterAssetPartnerPrice::whereIn('master_asset_id', $masterAssets->pluck('id'))
            ->whereIn('org_partner_id', $partners->pluck('id'))
            ->get()
            ->groupBy('master_asset_id');

        $siblings = $this->siblings($masterAssets, $partners, $masterShop);

        $rows = [];
        foreach ($masterAssets as $masterAsset) {
            $listPrice = (float) (data_get($masterAsset->master_prices, $baseCurrencyCode.'.value') ?? $masterAsset->price ?? 0);
            $custom    = ($customPrices->get($masterAsset->id) ?? collect())->keyBy('org_partner_id');

            $rows[$masterAsset->id] = [
                'list_price' => $listPrice,
                'cost'       => $masterAsset->effective_cost !== null && $costExchange ? round((float) $masterAsset->effective_cost * $costExchange, 4) : null,
                'prices'     => $partners->mapWithKeys(fn (OrgPartner $orgPartner) => [
                    $orgPartner->id => $custom->has($orgPartner->id) ? (float) $custom->get($orgPartner->id)->price : null,
                ])->all(),
                'sibling'    => $siblings[mb_strtolower($masterAsset->code)] ?? null,
                'overhead'   => self::estimatedOverhead($masterAsset),
            ];
        }

        $shopCurrenciesByOrganisation = Shop::whereIn('shops.organisation_id', $partners->pluck('organisation_id'))
            ->where('shops.type', ShopTypeEnum::B2B)
            ->where('shops.state', ShopStateEnum::OPEN)
            ->join('currencies', 'currencies.id', 'shops.currency_id')
            ->distinct()
            ->get(['shops.organisation_id', 'currencies.code'])
            ->groupBy('organisation_id')
            ->map(fn (Collection $shops) => $shops->pluck('code')->values()->all());

        $ratesFromBase = Currency::whereIn('code', $shopCurrenciesByOrganisation->flatten()->unique()->values())->get()
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => $this->exchange($baseCurrency, $currency)])
            ->filter()
            ->all();

        return [
            'meta' => [
                'seller_code'      => $seller->code,
                'rates_from_base'  => $ratesFromBase,
                'base_currency'    => ['code' => $baseCurrency->code, 'symbol' => $baseCurrency->symbol],
                'partner_discount' => $partnerDiscount,
                'partners'         => $partners->map(fn (OrgPartner $orgPartner) => [
                    'org_partner_id'  => $orgPartner->id,
                    'code'            => $orgPartner->organisation->code,
                    'name'            => $orgPartner->organisation->name,
                    'currency_code'   => $orgPartner->organisation->currency->code,
                    'currency_symbol' => $orgPartner->organisation->currency->symbol,
                    'from_base_rate'  => $this->exchange($baseCurrency, $orgPartner->organisation->currency) ?? 1.0,
                    'shop_currencies' => $shopCurrenciesByOrganisation->get($orgPartner->organisation_id, []),
                ])->values()->all(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * The master behind the partners' B2B products of the same code; when several masters share a
     * code the one most of those products follow wins.
     *
     * @param Collection<int, MasterAsset> $masterAssets
     * @param Collection<int, OrgPartner>  $partners
     *
     * @return array<string, array<string, mixed>> keyed by lower-cased code
     */
    private function siblings(Collection $masterAssets, Collection $partners, MasterShop $masterShop): array
    {
        $codes = $masterAssets->pluck('code')->map(fn (string $code) => mb_strtolower($code))->unique()->values();
        if ($codes->isEmpty()) {
            return [];
        }

        $siblingIds = DB::table('products')
            ->join('shops', 'shops.id', 'products.shop_id')
            ->whereIn('shops.organisation_id', $partners->pluck('organisation_id'))
            ->where('shops.type', ShopTypeEnum::B2B->value)
            ->where('shops.master_shop_id', '!=', $masterShop->id)
            ->whereIn('products.state', [ProductStateEnum::ACTIVE->value, ProductStateEnum::DISCONTINUING->value])
            ->whereNotNull('products.master_product_id')
            ->whereIn(DB::raw('lower(products.code)'), $codes)
            ->groupBy(DB::raw('lower(products.code)'), 'products.master_product_id')
            ->orderByRaw('count(*) desc')
            ->get([DB::raw('lower(products.code) as code'), 'products.master_product_id'])
            ->unique('code')
            ->pluck('master_product_id', 'code');

        if ($siblingIds->isEmpty()) {
            return [];
        }

        $siblingMasters = MasterAsset::whereIn('id', $siblingIds->values())->with('masterShop')->get()->keyBy('id');

        $currenciesByMasterShop = $siblingMasters->pluck('masterShop')->unique('id')
            ->mapWithKeys(fn (MasterShop $siblingMasterShop) => [$siblingMasterShop->id => GetMasterShopCurrenciesRate::run($siblingMasterShop)]);

        $groupCurrency      = $masterShop->group->currency;
        $currencyCodes      = $currenciesByMasterShop->flatMap(fn (Collection $currencies) => $currencies->keys())->unique();
        $costExchangeByCode = Currency::whereIn('code', $currencyCodes)->get()
            ->mapWithKeys(fn (Currency $currency) => [$currency->code => $this->exchange($groupCurrency, $currency)]);

        return $siblingIds->mapWithKeys(function (int $siblingId, string $code) use ($siblingMasters, $currenciesByMasterShop, $costExchangeByCode) {
            /** @var MasterAsset|null $sibling */
            $sibling = $siblingMasters->get($siblingId);
            if (!$sibling) {
                return [];
            }

            $currencies = $currenciesByMasterShop->get($sibling->master_shop_id);

            return [
                $code => [
                    'id'            => $sibling->id,
                    'code'          => $sibling->code,
                    'master_shop'   => $sibling->masterShop->name,
                    'units'         => (float) $sibling->units,
                    'master_prices' => $sibling->master_prices,
                    'master_rrps'   => $sibling->master_rrps,
                    'currencies'    => $currencies,
                    'costs'         => $sibling->effective_cost === null ? null : $currencies->map(
                        fn ($rate, string $currencyCode) => ($costExchangeByCode[$currencyCode] ?? null) ? round((float) $sibling->effective_cost * $costExchangeByCode[$currencyCode], 2) : null
                    ),
                    'update_route'  => [
                        'name'       => 'grp.models.master_asset.prices.update',
                        'parameters' => ['masterAsset' => $sibling->id],
                    ],
                ],
            ];
        })->all();
    }

    private function exchange(Currency $from, Currency $to): ?float
    {
        if ($from->id === $to->id) {
            return 1.0;
        }

        $exchange = GetCurrencyExchange::run($from, $to);

        return $exchange ? (float) $exchange : null;
    }
}
