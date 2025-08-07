<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:05 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Http\Resources\Inventory\OrgStockResource;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse, OrgStock $orgStock)
    {
        $orgStock->load('locationOrgStocks');
        $dataTradeUnits = [];
        if ($orgStock->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($orgStock->tradeUnits);
        }
        return collect(
            [
                'trade_units' => $dataTradeUnits,
                'stock_data' => $this->stockData($warehouse, $orgStock),
                'contactCard'              => OrgStockResource::make($orgStock)->getArray(),
                'locationRoute'            => [
                    'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                    'parameters' => [
                        'organisation' => $warehouse->organisation->slug,
                        'warehouse'    => $warehouse->slug
                    ]
                ],
                'associateLocationRoute'  => [
                    'method'     => 'post',
                    'name'       => 'grp.models.org_stock.location.store',
                    'parameters' => [
                        'orgStock' => $orgStock->id
                    ]
                ],
                'disassociateLocationRoute' => [
                    'method'    => 'delete',
                    'name'      => 'grp.models.location_org_stock.delete',
                ],
                'auditRoute' => [
                    'method'    => 'patch',
                    'name'      => 'grp.models.location_org_stock.audit',
                ],
                'moveLocationRoute' => [
                    'method'    => 'patch',
                    'name'      => 'grp.models.location_org_stock.move',
                ]
            ]
        );
    }

    public function stockData(Warehouse $warehouse, OrgStock $orgStock): array
    {
        $locationData = $orgStock->locationOrgStocks->map(function (LocationOrgStock $locationOrgStock) {
            return [
                'id' => $locationOrgStock->id,
                'name' => $locationOrgStock->location->code,
                'lastAudit' => $locationOrgStock->audited_at,
                'stock' => $locationOrgStock->quantity,
                'isAudited' => !is_null($locationOrgStock->audited_at)
            ];
        })->toArray();

        return [
            'stock_in_locations' => $orgStock->quantity_in_locations,
            'stock_in_process' => $orgStock->stats->number_stock_deliveries_state_in_process,
            'stock_in_picked' => $orgStock->stats->number_stock_deliveries_state_ready_to_ship,
            'stock_available' => $orgStock->quantity_in_locations -
                ($orgStock->stats->number_stock_deliveries_state_in_process +
                    $orgStock->stats->number_stock_deliveries_state_ready_to_ship),
            'stock_value' => $orgStock->value_in_locations,
            'current_cost' => $orgStock->unit_cost,
            'locations' => $locationData
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }
}
