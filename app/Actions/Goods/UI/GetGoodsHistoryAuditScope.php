<?php

/*
 * Author Louis Perez
 * Created on 29-09-2026-13h-44m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\UI;

use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetGoodsHistoryAuditScope
{
    use AsObject;

    /**
     * @return array<int, array{type: string, labels: array<int, string>, shops: null}>
     */
    public function forStock(Stock $stock): array
    {
        return [
            ['type' => 'Stock', 'labels' => [$stock->id => $stock->code], 'shops' => null],
            ['type' => 'OrgStock', 'labels' => $this->orgStockLabels(fn ($query) => $query->where('org_stocks.stock_id', $stock->id), withCode: false), 'shops' => null],
        ];
    }

    /**
     * @return array<int, array{type: string, labels: array<int, string>, shops: null}>
     */
    public function forStockFamily(StockFamily $stockFamily): array
    {
        return [
            ['type' => 'StockFamily', 'labels' => [$stockFamily->id => $stockFamily->code], 'shops' => null],
            ['type' => 'OrgStock', 'labels' => $this->orgStockLabels(fn ($query) => $query->where('stocks.stock_family_id', $stockFamily->id), withCode: true), 'shops' => null],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function orgStockLabels(callable $constrain, bool $withCode): array
    {
        $query = DB::table('org_stocks')
            ->join('stocks', 'stocks.id', 'org_stocks.stock_id')
            ->join('organisations', 'organisations.id', 'org_stocks.organisation_id')
            ->select(['org_stocks.id', 'stocks.code', 'organisations.code as organisation_code']);
        $constrain($query);

        return $query->get()
            ->mapWithKeys(fn ($row) => [$row->id => $withCode ? $row->code.' · '.$row->organisation_code : $row->organisation_code])
            ->all();
    }
}
