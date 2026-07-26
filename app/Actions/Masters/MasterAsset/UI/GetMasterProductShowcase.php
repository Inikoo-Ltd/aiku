<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Masters\MasterAsset\UI;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Masters\MasterAsset\Json\GetPriceRebelProducts;
use App\Actions\Masters\MasterShop\GetMasterShopCurrenciesRate;
use App\Models\Helpers\Currency;
use App\Actions\Traits\HasBucketImages;
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
            'ingredients'       => $masterAsset->marketing_ingredients,
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
        if ($masterAsset->not_for_sale_from_trade_unit) {
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
            'masterProduct'       => MasterProductResource::make($masterAsset)->resolve(),
            'properties'          => $properties,
            'trade_units'         => $dataTradeUnits,
            'rebel_prices'        => GetPriceRebelProducts::run($masterAsset, ['type' => 'price']),
            'rebel_rrp'           => GetPriceRebelProducts::run($masterAsset, ['type' => 'rrp']),
            'gpsr'                => $gpsr,
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
    private function getPricingCosts(MasterAsset $masterAsset): ?array
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

    private function getDataTradeUnit(MasterAsset $masterAsset): array
    {
        $packedIn = $masterAsset->getStockPackedInByTradeUnit();

        return $masterAsset->tradeUnits->map(function (TradeUnit $tradeUnit) use ($packedIn) { //louis need fix it
            return array_merge(
                ['pick_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($tradeUnit->pivot->quantity / Arr::get($packedIn, $tradeUnit->id, 1))), Arr::get($packedIn, $tradeUnit->id, 1))],
                GetTradeUnitShowcase::run($tradeUnit)
            );
        })->toArray();
    }


}
