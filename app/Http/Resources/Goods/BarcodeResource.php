<?php

/*
 * Author Louis Perez
 * Created on 24-06-2026-09h-43m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Barcode;

class BarcodeResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Barcode $barcode */
        $barcode = $this->resource;

        $tradeUnit = null;
        if ($barcode->relationLoaded('tradeUnitActive') && $barcode->tradeUnitActive->first()) {
            $tradeUnit = TradeUnitResource::make($barcode->tradeUnitActive->first())->resolve();
        }

        return [
            'number'        => $barcode->number,
            'type'          => strtoupper($barcode->type),
            'status'        => $barcode->status->label(),
            'status_icon'   => $barcode->status->icon(),
            'note'          => $barcode->note,
            'trade_unit'    => $tradeUnit
        ];
    }
}
