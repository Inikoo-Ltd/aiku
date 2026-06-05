<?php

/*
 * author Louis Perez
 * created on 06-05-2026-14h-08m
 * github: https://github.com/louis-perez
 * copyright 2025
*/
namespace App\Actions\Goods\TradeUnitFamily;

use App\Models\Catalogue\ProductCategory;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitFamilyForFamilies
{
    use AsObject;

    public function handle(MasterProductCategory|ProductCategory $parent): array
    {
        $selectOptions = [];
        foreach(TradeUnitFamily::all() as $tradeUnitFamily) {
            data_set($selectOptions, $tradeUnitFamily->id,
                [
                    'label' => $tradeUnitFamily->code.' ('.$tradeUnitFamily->name.')',
                    'id'    => $tradeUnitFamily->id
                ]
            );
        }

        return $selectOptions;
    }
}