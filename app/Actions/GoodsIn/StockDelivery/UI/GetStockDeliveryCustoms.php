<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryCostTypeEnum;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryCustomsLine;
use App\Models\GoodsIn\StockDeliveryItem;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockDeliveryCustoms
{
    use AsObject;

    public function handle(StockDelivery $stockDelivery): array
    {
        $lines = $stockDelivery->customsLines()->orderBy('id')->get();
        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with(['orgStock.tradeUnits.tariffCodeOverrides', 'supplierProduct:id,code,name'])
            ->orderBy('id')
            ->get();

        $orgExchange = (float) ($stockDelivery->org_exchange ?: 1);
        $dutyRow     = $stockDelivery->costs()->where('type', StockDeliveryCostTypeEnum::DUTY)->first();

        return [
            'customs_mrn'         => $stockDelivery->customs_mrn,
            'customs_released_at' => $stockDelivery->customs_released_at?->toDateString(),
            'org_currency'        => $stockDelivery->organisation->currency->code,
            'delivery_currency'   => $stockDelivery->currency->code,
            'org_exchange'        => $orgExchange,
            'duty_cost'           => $dutyRow && !$dutyRow->is_na ? round($dutyRow->amountInDeliveryCurrency() * $orgExchange, 2) : null,
            'can_edit'            => !$stockDelivery->is_costed,
            'lines'               => $lines->map(fn (StockDeliveryCustomsLine $line) => [
                'id'            => $line->id,
                'tariff_code'   => $line->tariff_code,
                'description'   => $line->description,
                'duty_rate'     => (float) $line->duty_rate,
                'customs_value' => (float) $line->customs_value,
                'duty_amount'   => (float) $line->duty_amount,
                'import_vat'    => $line->import_vat === null ? null : (float) $line->import_vat,
                'allocated'     => round((float) $items->where('stock_delivery_customs_line_id', $line->id)->sum('cost_duties') * $orgExchange, 2),
                'items_value'   => round((float) $items->where('stock_delivery_customs_line_id', $line->id)->sum(fn (StockDeliveryItem $item) => (float) ($item->cost_items ?? $item->net_amount)) * $orgExchange, 2),
            ])->values()->all(),
            'items'               => $items->map(function (StockDeliveryItem $item) use ($orgExchange) {
                $tradeUnit = $item->orgStock?->tradeUnits->first();

                return [
                    'id'                             => $item->id,
                    'code'                           => $item->orgStock?->code ?? $item->supplierProduct?->code,
                    'name'                           => $item->orgStock?->name ?? $item->supplierProduct?->name,
                    'tariff_code'                    => $tradeUnit?->getTariffCodeForOrganisation($item->organisation_id),
                    'stock_delivery_customs_line_id' => $item->stock_delivery_customs_line_id,
                    'value'                          => round((float) ($item->cost_items ?? $item->net_amount) * $orgExchange, 2),
                    'cost_duties'                    => $item->cost_duties === null ? null : round((float) $item->cost_duties * $orgExchange, 2),
                    'updateRoute'                    => [
                        'name'       => 'grp.models.stock-delivery-item.customs-line',
                        'parameters' => ['stockDeliveryItem' => $item->id],
                        'method'     => 'patch',
                    ],
                ];
            })->values()->all(),
            'updateRoute'         => [
                'name'       => 'grp.models.stock-delivery.customs.update',
                'parameters' => ['stockDelivery' => $stockDelivery->id],
                'method'     => 'patch',
            ],
        ];
    }
}
