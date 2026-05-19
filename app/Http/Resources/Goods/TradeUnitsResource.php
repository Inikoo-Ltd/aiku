<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Mar 2024 20:31:47 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use App\Models\Goods\TradeUnit;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $slug
 * @property string $type
 * @property string $name
 * @property mixed $number_current_stocks
 * @property mixed $number_current_products
 * @property mixed $id
 * @property mixed $status
 * @property mixed $quantity
 * @property mixed $marketing_weight
 * @property mixed $sales_grp_currency_external
 * @property mixed $sales_grp_currency_external_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 * @property mixed $health_rank
 */
class TradeUnitsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var TradeUnit $tradeUnit*/
        $tradeUnit = $this->resource;

        $additionalData = [];

        if ($tradeUnit->relationLoaded('brands')) {
            $additionalData['brands']   = $tradeUnit->brands->select([
                    'slug',
                    'name',
                ])->first();
        }

        if ($tradeUnit->relationLoaded('tags')) {
            $additionalData['tags']   = $tradeUnit->tags->map(function ($item) {
                $hash = 0;
                for ($i = 0; $i < strlen($item->name); $i++) {
                    $hash = ord($item->name[$i]) + (($hash << 5) - $hash);
                }
                $color = "#";
                for ($i = 0; $i < 3; $i++) {
                    $value = ($hash >> ($i * 8)) & 0xFF;
                    $color .= str_pad(dechex($value), 2, "0", STR_PAD_LEFT);
                }

                return [
                    'slug'          => $item->slug,
                    'name'          => $item->name,
                    'class_color'   => $color
                ];
            })->toArray();
        }

        return [
            'slug'                              => $tradeUnit->slug,
            'code'                              => $tradeUnit->code,
            'name'                              => $tradeUnit->name,
            'type'                              => $tradeUnit->type,
            'number_current_stocks'             => $tradeUnit->number_current_stocks,
            'number_current_products'           => $tradeUnit->number_current_products,
            'id'                                => $tradeUnit->id,
            'quantity'                          => trimDecimalZeros($tradeUnit->quantity),
            'status'                            => $tradeUnit->status,
            'status_icon'                       => $tradeUnit->status ? $tradeUnit->status->icon()[$tradeUnit->status->value] : null,
            'media'                             => null,
            'sales_grp_currency_external'       => $tradeUnit->sales_grp_currency_external ?? 0,
            'sales_grp_currency_external_ly'    => $tradeUnit->sales_grp_currency_external_ly ?? 0,
            'sales_grp_currency_external_delta' => $this->calculateDelta($tradeUnit->sales_grp_currency_external ?? 0, $tradeUnit->sales_grp_currency_external_ly ?? 0),
            'invoices'                          => $tradeUnit->invoices ?? 0,
            'invoices_ly'                       => $tradeUnit->invoices_ly ?? 0,
            'invoices_delta'                    => $this->calculateDelta($tradeUnit->invoices ?? 0, $tradeUnit->invoices_ly ?? 0),
            'health_rank'                       => $tradeUnit->health_rank ? $tradeUnit->health_rank->stateIcon()[$tradeUnit->health_rank->value] : null,
            'marketing_weight'                  => $tradeUnit->marketing_weight,
            'marketing_dimensions'              => $tradeUnit->marketing_dimensions,
            ...$additionalData
        ];
    }

    private function calculateDelta(float $current, float $previous): ?array
    {
        if (!$previous || $previous == 0) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value'       => $delta,
            'formatted'   => number_format($delta, 1).'%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
        ];
    }
}
