<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Barcode;
use App\Models\Helpers\Brand;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchTradeUnits
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query): array
    {
        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? ($document['status'] ?? null),
        ];

        return [
            'scope'   => 'trade_units',
            'results' => [
                'trade_units'         => array_map($mapCodeNameState, $this->rawDocuments(TradeUnit::search($query))),
                'trade_unit_families' => array_map($mapCodeNameState, $this->rawDocuments(TradeUnitFamily::search($query))),
                'brands'              => array_map($mapCodeNameState, $this->rawDocuments(Brand::search($query))),
                'tags'                => array_map($mapCodeNameState, $this->rawDocuments(Tag::search($query))),
                'barcodes'            => array_map(static fn (array $document) => [
                    'id'    => (int)$document['id'],
                    'code'  => $document['number'] ?? null,
                    'state' => $document['state'] ?? null,
                ], $this->rawDocuments(Barcode::search($query))),
            ],
        ];
    }


}
