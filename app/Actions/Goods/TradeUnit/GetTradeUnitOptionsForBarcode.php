<?php

/*
 * Author Louis Perez
 * Created on 29-06-2026-14h-17m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\TradeUnit;

use App\Enums\Goods\TradeUnit\TradeUnitStatusEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitOptionsForBarcode
{
    use AsObject;

    public function handle(Group $parent)
    {
        return $parent
            ->tradeUnits()
            ->whereNull('barcode_id')
            ->whereNot('status', TradeUnitStatusEnum::DISCONTINUED)
            ->get()
            ->mapWithKeys(fn ($tradeUnit) => [$tradeUnit->id => [
                'label'     => $tradeUnit->code." - ".$tradeUnit->name,
                'id'        => $tradeUnit->id,
            ]]);
    }
}
