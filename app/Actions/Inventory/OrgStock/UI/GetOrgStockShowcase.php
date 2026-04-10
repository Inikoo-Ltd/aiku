<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:05 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Http\Resources\Inventory\LocationOrgStocksResource;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketImages;

class GetOrgStockShowcase
{
    use AsObject;
    use HasBucketImages;
    use CalculatesOrgStockHistories;

    public function handle(Warehouse $warehouse, OrgStock $orgStock): \Illuminate\Support\Collection
    {
        $orgStock->load('locationOrgStocks');
        $dataTradeUnits = [];
        if ($orgStock->tradeUnits) {
            $dataTradeUnits = $this->getDataTradeUnit($orgStock->tradeUnits);
        }

        $locations = LocationOrgStocksResource::collection($orgStock->locationOrgStocks()->with(['location', 'organisation', 'warehouse'])->get())->toArray(request());
        usort($locations, function ($a, $b) {
            return $a['code'] <=> $b['code'];
        });

        return collect(
            [
                'trade_units'               => $dataTradeUnits,
                'stocks_management'         => [
                    'routes'         => [
                        'location_route'             => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.index.excluded_in_org_stock',
                            'parameters' => [
                                'organisation' => $warehouse->organisation->slug,
                                'warehouse'    => $warehouse->slug,
                                'orgStock'     => $orgStock->slug
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
                        'set_location_as_picking_priority_route'      => [],  // TODO
                        'add_parts_location_note'      => [],  // TODO
                    ],
                    'stock_cost'     => [
                            'cost_stock_price_per_unit' => $orgStock->unit_cost * ($orgStock->tradeUnits()->first()?->pivot?->quantity ?? 1),
                            'cost_stock_price_outer' => $orgStock->unit_cost * $orgStock->quantity_available,
                            'cost_current_price_per_unit' => $orgStock->unit_cost,
                            'cost_current_price_outer' => $orgStock->unit_cost * $orgStock->quantity_available,
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
                    'locations'      => $locations,
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
            'stock_available'    => $orgStock->quantity_in_locations - ($orgStock->stats->number_stock_deliveries_state_in_process +  $orgStock->stats->number_stock_deliveries_state_ready_to_ship),
            'stock_value'        => $orgStock->value_in_locations,
            'current_cost'       => $orgStock->unit_cost,
            // 'locations'          => $locationData
        ];
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return [
                'slug' => $tradeUnit->slug,
                'status' => $tradeUnit->status,
                'code' => $tradeUnit->code,
                'id' => $tradeUnit->id,
                'stock' => $tradeUnit->orgStocks->sum('quantity_in_locations'),
                'name' => $tradeUnit->name,
                'images' => $this->getImagesData($tradeUnit),
            ];
        })->toArray();
    }
}
