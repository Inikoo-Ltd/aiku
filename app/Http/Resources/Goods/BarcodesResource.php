<?php

/*
 * Author Louis Perez
 * Created on 19-06-2026-10h-24m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Barcode;

class BarcodesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Barcode $barcode */
        $barcode = $this->resource;

        $tradeUnits = [];
        if ($barcode->relationLoaded('tradeUnitsActive')) {
            $tradeUnits = $barcode->tradeUnitsActive->select([
                'slug',
                'code',
                'name'
            ])->toArray();
        }

        return [
            'number'        => $barcode->number,
            'slug'          => $barcode->slug,
            'type'          => strtoupper($barcode->type),
            'status'        => $barcode->status->value,
            'status_icon'   => $barcode->status->icon(),
            'note'          => $barcode->note,
            'trade_units'   => $tradeUnits
        ];
    }
}
