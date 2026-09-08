<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Feb 2025 18:21:56 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Models\Goods\Stock;
use Lorisleiva\Actions\ActionRequest;

trait WithStockNavigation
{
    use WithBucketNavigation;

    public function getPrevious(Stock $stock, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getStockNeighbour($stock, $request, forward: false), $request->route()->getName());
    }

    public function getNext(Stock $stock, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getStockNeighbour($stock, $request, forward: true), $request->route()->getName());
    }

    private function getStockNeighbour(Stock $stock, ActionRequest $request, bool $forward): ?Stock
    {
        if (!$stock->code) {
            return null;
        }

        $routeName = $request->route()->getName();
        $query     = Stock::query();

        if ($routeName === 'grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show') {
            $query->where('stock_family_id', $stock->stockFamily->id);
        }

        $bucket = $request->input('bucket');

        if (!$bucket && preg_match('/\.(\w+)_stocks\./', $routeName, $matches)) {
            $bucket = $matches[1];
        }

        $state = match ($bucket) {
            'active'        => StockStateEnum::ACTIVE,
            'discontinuing' => StockStateEnum::DISCONTINUING,
            'discontinued'  => StockStateEnum::DISCONTINUED,
            'in_process'    => StockStateEnum::IN_PROCESS,
            default         => null,
        };

        if ($state) {
            $query->where('stocks.state', $state);
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $stock,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code'        => 'stocks.code',
                'name'        => 'stocks.name',
                'description' => 'stocks.description',
            ],
            defaultSort: ['stocks.code', false],
            forward: $forward
        );
    }

    private function getNavigation(?Stock $stock, string $routeName): ?array
    {
        if (!$stock) {
            return null;
        }


        return match ($routeName) {
            'grp.goods.stocks.show',
            'grp.goods.stocks.active_stocks.show',
            'grp.goods.stocks.in_process_stocks.show',
            'grp.goods.stocks.discontinuing_stocks.show',
            'grp.goods.stocks.discontinued_stocks.show',
            'grp.goods.stocks.edit',
            'grp.goods.stocks.active_stocks.edit',
            'grp.goods.stocks.in_process_stocks.edit',
            'grp.goods.stocks.discontinuing_stocks.edit',
            'grp.goods.stocks.discontinued_stocks.edit',
            => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stock' => $stock->slug
                    ]
                ]
            ],
            'grp.goods.org_stock_families.show.stocks.show' => [
                'label' => $stock->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'stockFamily'   => $stock->stockFamily->slug,
                        'stock'         => $stock->slug
                    ]

                ]
            ]
        };
    }

}
