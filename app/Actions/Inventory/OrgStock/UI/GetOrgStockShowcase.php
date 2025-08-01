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

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }
}
