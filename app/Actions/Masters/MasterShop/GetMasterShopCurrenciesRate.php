<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Currency;
use App\Models\Masters\MasterShop;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterShopCurrenciesRate
{
    use AsObject;

    /**
     * The base major is the one the minor currencies actually follow (ties broken by
     * config order), falling back to the first configured major. Same rule as the
     * price editor UI, so the legacy price/rrp columns always mirror the base row.
     *
     * @param array<string, array{is_major?: bool, major?: string, exchange?: float}> $priceExchanges
     */
    public static function baseCurrencyCode(array $priceExchanges): ?string
    {
        $majorCodes = collect($priceExchanges)
            ->filter(fn (array $exchangeData) => $exchangeData['is_major'] ?? false)
            ->keys();

        $mostFollowed = collect($priceExchanges)
            ->pluck('major')
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first(fn (string $code) => $majorCodes->contains($code));

        return $mostFollowed ?? $majorCodes->first();
    }

    /**
     * @param bool $onlyOpenShops restrict the currency set to open shops. Master product creation
     *                            seeds prices for shops that will actually sell, while the edit,
     *                            bulk edit and family pages expose every currency the master shop
     *                            has ever had a shop for, so a closed shop's prices stay editable.
     */
    public function handle(MasterShop $masterShop, bool $onlyOpenShops = false): Collection
    {
        $shopCurrencies = Shop::where('master_shop_id', $masterShop->id)
            ->when($onlyOpenShops, fn ($query) => $query->where('state', ShopStateEnum::OPEN))
            ->select('currency_id')
            ->distinct()
            ->get();

        $priceExchanges = $masterShop->price_exchanges ?? [];
        $currencies     = Currency::whereIn('id', $shopCurrencies)->get();

        return $currencies->mapWithKeys(function (Currency $currency) use ($priceExchanges) {
            $exchangeData = $priceExchanges[$currency->code] ?? null;
            $isMajor      = (bool)($exchangeData['is_major'] ?? false);

            return [
                $currency->code => [
                    'ratio_eur'       => $isMajor ? 1.0 : ($exchangeData['exchange'] ?? null),
                    'currency'        => $currency->code,
                    'currency_symbol' => $currency->symbol,
                    'currency_id'     => $currency->id,
                    'fraction_digits' => $isMajor ? $currency->fraction_digits : (int)($exchangeData['fraction_digits'] ?? $currency->fraction_digits),
                    'is_major'        => $isMajor,
                    'major'           => $isMajor ? null : ($exchangeData['major'] ?? null),
                ]
            ];
        });
    }
}
