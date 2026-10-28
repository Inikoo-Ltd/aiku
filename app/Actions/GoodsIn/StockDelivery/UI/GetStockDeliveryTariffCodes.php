<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCustomsLine;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\Helpers\TariffCode;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockDeliveryTariffCodes
{
    use AsObject;

    public function handle(StockDelivery $stockDelivery): array
    {
        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with(['orgStock.stock', 'orgStock.tradeUnits.tariffCodeOverrides', 'orgStock.tradeUnits.countryOrigin', 'supplierProduct:id,code'])
            ->get();

        return [
            'org_currency'       => $stockDelivery->organisation->currency->code,
            'description_locale' => TariffCode::nameLocaleFor($stockDelivery->organisation->language->code),
            'rows'               => $this->rows($items, (float) ($stockDelivery->org_exchange ?: 1)),
        ];
    }

    /**
     * One row per tariff code and country of origin, the list a clearance agent declares from, with the
     * tariff code described in each of TariffCode::NAME_LOCALES. Quantities are the shipped units.
     *
     * @param Collection<int, StockDeliveryItem> $items
     *
     * @return array<int, array{tariff_code: string|null, origin: string|null, descriptions: array<string, string|null>, codes: array<int, string>, mismatches: array<int, string>, units: float, weight: float, value: float}>
     */
    private function rows(Collection $items, float $orgExchange): array
    {
        return $items
            ->groupBy(function (StockDeliveryItem $item) {
                $tradeUnit = $item->orgStock?->tradeUnits->first();

                return StockDeliveryCustomsLine::normaliseTariffCode($item->orgStock?->getTariffCode()).'|'.($tradeUnit?->countryOrigin?->code ?? $tradeUnit?->country_of_origin);
            })
            ->map(function (Collection $group, string $key) use ($orgExchange) {
                [$tariffCode, $origin] = array_map(fn (string $value) => $value === '' ? null : $value, explode('|', $key));

                return [
                    'tariff_code'  => $tariffCode,
                    'origin'       => $origin,
                    'descriptions' => TariffCode::descriptionsFor($tariffCode),
                    'codes'        => $group->map(fn (StockDeliveryItem $item) => $item->orgStock?->code ?? $item->supplierProduct?->code)->filter()->unique()->sort()->values()->all(),
                    'mismatches'   => $group->filter(fn (StockDeliveryItem $item) => $item->orgStock?->hasTariffCodeHeadingMismatch())
                        ->map(fn (StockDeliveryItem $item) => $item->orgStock->code.': '.$item->orgStock->tradeUnits->first()->tariff_code)->unique()->sort()->values()->all(),
                    'units'        => (float) $group->sum('unit_quantity'),
                    'weight'       => round($group->sum(fn (StockDeliveryItem $item) => (float) $item->unit_quantity / $item->unitsPerSko() * (float) $item->orgStock?->stock?->gross_weight) / 1000, 3),
                    'value'        => round((float) $group->sum(fn (StockDeliveryItem $item) => (float) ($item->cost_items ?? $item->net_amount)) * $orgExchange, 2),
                ];
            })
            ->sortBy(fn (array $row) => ($row['tariff_code'] === null ? '0' : '1').$row['tariff_code'].'|'.$row['origin'])
            ->values()
            ->all();
    }
}
