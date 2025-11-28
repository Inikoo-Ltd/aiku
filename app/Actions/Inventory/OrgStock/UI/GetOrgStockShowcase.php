<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:05 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Goods\TradeUnit\UI\GetTradeUnitShowcase;
use App\Http\Resources\Inventory\LocationOrgStocksResource;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStockShowcase
{
    use AsObject;

    public function handle(Warehouse $warehouse, OrgStock $orgStock): \Illuminate\Support\Collection
    {
        $orgStock->load('locationOrgStocks');
        $dataTradeUnits = [];
        if ($orgStock->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($orgStock->tradeUnits);
        }

        // dd($orgStock);
        return collect(
            [
                // 'stock_data'                => $this->orgStockData($orgStock),
                'trade_units'               => $dataTradeUnits,
                'stocks_management'         => [
                    'routes'         => [
                        'location_route'             => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                            'parameters' => [
                                'organisation' => $warehouse->organisation->slug,
                                'warehouse'    => $warehouse->slug
                            ]
                        ],
                        'associate_location_route'    => [
                            'method'     => 'post',
                            'name'       => 'grp.models.org_stock.location.store',
                            'parameters' => [
                                'orgStock' => $orgStock->id
                            ]
                        ],
                        'disassociate_location_route' => [
                            'method' => 'delete',
                            'name'   => 'grp.models.location_org_stock.delete',
                        ],
                        'audit_route'                => [
                            'method' => 'patch',
                            'name'   => 'grp.models.location_org_stock.audit',
                            'parameters' => [
                                'locationOrgStock' => null, // Fill in FE
                            ]
                        ],
                        'move_location_route'         => [
                            'method' => 'patch',
                            'name'   => 'grp.models.location_org_stock.move',
                        ],
                        // 'fetch_locations'         => [
                        //     'name'       => 'xxxxxxxxxxxxxxxxxx',
                        //     'parameters' => []
                        // ],  // TODO: Artha, fetch locations list in the warehouse
                        // 'submit_audit_stocks'     => [
                        //     'name'       => 'xxxxxxxxxxxxxxxxxx',
                        //     'parameters' => []
                        // ],  // TODO: Artha, submit audit stocks
                        // 'update_stocks_locations' => [
                        //     'name'       => 'xxxxxxxxxxxxxxxxxx',
                        //     'parameters' => []
                        // ],  // TODO: Artha, attach and detach the stocks to locations
                    ],
                    'summary'        => [
                        'quantity_in_locations'        => [
                            'icon_state' => [
                                'icon'    => 'fal fa-inventory',
                                'tooltip' => __("Stock in locations"),
                            ],
                            'value'      => $orgStock->quantity_in_locations,
                        ],
                        'quantity_in_submitted_orders' => [
                            'icon_state' => [
                                'icon'    => 'fas fa-shopping-cart',
                                'tooltip' => __("Reserved paid parts in process by customer services"),
                            ],
                            'value'      => $orgStock->quantity_in_submitted_orders
                        ],
                        'quantity_to_be_picked'        => [
                            'icon_state' => [
                                'icon'    => 'fas fa-shopping-basket',
                                'tooltip' => __("Parts been picked"),
                            ],
                            'value'      => $orgStock->quantity_to_be_picked
                        ],
                        'quantity_available'           => [
                            'icon_state' => [
                                'icon'    => 'fal fa-dot-circle',
                                'class'   => 'animate-pulse text-green-500',
                                'tooltip' => __("Stock available for sale"),
                            ],
                            'value'      => $orgStock->quantity_available
                        ],
                    ],
                    'locations'      => LocationOrgStocksResource::collection($orgStock->locationOrgStocks)->toArray(request()),
                ]
            ]
        );
    }

    public function orgStockData(OrgStock $orgStock): array
    {
        // $locationData = $orgStock->locationOrgStocks->map(function (LocationOrgStock $locationOrgStock) {
        //     return [
        //         'id'        => $locationOrgStock->id,
        //         'name'      => $locationOrgStock->location->code,
        //         'lastAudit' => $locationOrgStock->audited_at,
        //         'stock'     => $locationOrgStock->quantity,
        //         'isAudited' => !is_null($locationOrgStock->audited_at)
        //     ];
        // })->toArray();

        return [
            // 'stock_in_locations' => $orgStock->quantity_in_locations,
            'stock_in_process'   => $orgStock->stats->number_stock_deliveries_state_in_process,
            'stock_in_picked'    => $orgStock->stats->number_stock_deliveries_state_ready_to_ship,
            'stock_available'    => $orgStock->quantity_in_locations -
                ($orgStock->stats->number_stock_deliveries_state_in_process +
                    $orgStock->stats->number_stock_deliveries_state_ready_to_ship),
            'stock_value'        => $orgStock->value_in_locations,
            'current_cost'       => $orgStock->unit_cost,
            // 'locations'          => $locationData
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return GetTradeUnitShowcase::run($tradeUnit);
        })->toArray();
    }
}
