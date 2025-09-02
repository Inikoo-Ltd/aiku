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
                ],
                'stocks_management' => [
                    'routes'    => [
                        'fetch_locations'               => [],  // TODO: Artha, fetch locations list in the warehouse
                        'submit_audit_stocks'           => [],  // TODO: Artha, submit audit stocks
                        'update_stocks_locations'      => [],  // TODO: Artha, attach and detach the stocks to locations
                    ],
                    'summary' => [
                        'current_on_hand_stock' => [
                            'icon_state'    => [
                                'icon'          => 'fal fa-inventory',
                                'tooltip'       => __("Stock in locations"),
                            ],
                            'value'         => 2150  // TODO: Artha
                        ],
                        'part_current_stock_ordered_paid' => [
                            'icon_state'    => [
                                'icon'          => 'fas fa-shopping-cart',
                                'tooltip'       => __("Reserved paid parts in process by customer services"),
                            ],
                            'value'         => 2150  // TODO: Artha
                        ],
                        'current_stock_in_process' => [
                            'icon_state'    => [
                                'icon'          => 'fas fa-shopping-basket',
                                'tooltip'       => __("Parts been picked"),
                            ],
                            'value'         => 2150  // TODO: Artha
                        ],
                        'current_stock_available' => [
                            'icon_state'    => [
                                'icon'          => 'fal fa-dot-circle',
                                'class'         => 'animate-pulse text-green-500',
                                'tooltip'       => __("Stock available for sale"),
                            ],
                            'value'         => 2150  // TODO: Artha
                        ],
                    ],
                    'part_locations' => [  // TODO: Artha all of this 
                        [
                            'id' => 1,
                            'name' => 'E1',
                            'last_audit' => now(),
                            'stock' => 45,
                            'isAudited' => true
                        ],
                        [
                            'id' => 2,
                            'name' => 'E2',
                            'last_audit' => now(),
                            'stock' => 30,
                            'isAudited' => false
                        ]
                    ]
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
