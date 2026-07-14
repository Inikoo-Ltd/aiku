<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Goods\Stock;
use App\Models\Goods\StockFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchGoods
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query): array
    {
        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        return [
            'scope'   => 'goods',
            'results' => [
                'stocks'              => array_map($mapCodeNameState, $this->rawDocuments(Stock::search($query))),
                'stock_families'      => array_map($mapCodeNameState, $this->rawDocuments(StockFamily::search($query))),
                'trade_units'         => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['code'] ?? null,
                    'name'  => $document['name'] ?? null,
                    'state' => $document['status'] ?? null,
                ], $this->rawDocuments(TradeUnit::search($query))),
                'trade_unit_families' => array_map($mapCodeNameState, $this->rawDocuments(TradeUnitFamily::search($query))),
            ],
        ];
    }


}
