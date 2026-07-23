<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:14:05 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Http\Resources\Inventory\LocationOrgStocksResource;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementClassEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\Concerns\AsObject;
use App\Actions\Traits\HasBucketImages;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;

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

        $locations = LocationOrgStocksResource::collection($orgStock->locationOrgStocks()->with(['location', 'organisation', 'warehouse', 'orgStock'])->get())->toArray(request());
        usort($locations, function ($a, $b) {
            return $a['code'] <=> $b['code'];
        });

        return collect(
            [
                'trade_units'        => $dataTradeUnits,
                'currency_code'      => $orgStock->organisation->currency->code,
                'sales_data'         => GetOrgStockTimeSeriesData::run($orgStock),
                'barcodes'           => GetOrgStockBarcodes::run($orgStock),
                'label_route'        => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.label',
                    'parameters' => [
                        'organisation' => $warehouse->organisation->slug,
                        'warehouse'    => $warehouse->slug,
                        'orgStock'     => $orgStock->slug,
                    ],
                ],
                'is_quantity_excess' => $orgStock->quantity_status === OrgStockQuantityStatusEnum::EXCESS,
                'latest_movements'   => $this->getLatestMovements($orgStock),
                'stock_history_route' => [
                    'name'       => preg_replace('/\.(stock_history|procurement|products|delivery_notes|batch_codes)$/', '', request()->route()->getName()).'.stock_history',
                    'parameters' => request()->route()->originalParameters(),
                ],
                'stocks_management'  => [
                    'routes'          => [
                        'location_route'                         => [
                            'name'       => 'grp.org.warehouses.show.infrastructure.locations.index.excluded_in_org_stock',
                            'parameters' => [
                                'organisation' => $warehouse->organisation->slug,
                                'warehouse'    => $warehouse->slug,
                                'orgStock'     => $orgStock->slug
                            ]
                        ],
                        'associate_location_route'               => [
                            'method'     => 'post',
                            'name'       => 'grp.models.org_stock.location.store',
                            'parameters' => [
                                'orgStock' => $orgStock->id
                            ]
                        ],
                        'disassociate_location_route'            => [
                            'method' => 'delete',
                            'name'   => 'grp.models.location_org_stock.delete',
                        ],
                        'audit_route'                            => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.location_org_stock.audit',
                            'parameters' => [
                                'locationOrgStock' => null, // Fill in FE
                            ]
                        ],
                        'bulk_audit_route' => [
                            'method'     => 'patch',
                            'name'       => 'grp.models.org_stock.bulk_audit',
                            'parameters' => [
                                'orgStock' => null, // Fill in FE
                            ]
                        ],
                        'move_location_route'                    => [
                            'method' => 'patch',
                            'name'   => 'grp.models.location_org_stock.move',
                        ],
                        'set_location_as_picking_priority_route' => [],  // TODO
                        'add_parts_location_note'                => [],  // TODO
                    ],
                    'stock_cost'      => [
                        'sku_value'                 => $orgStock->sku_value,
                        'total_stock_value'         => $orgStock->sku_value * $orgStock->quantity_available,
                        'current_supplier_sku_cost' => $orgStock->current_supplier_sku_cost,
                    ],
                    'summary'         => [
                        'quantity_in_locations' => [
                            'icon_state' => [
                                'icon'    => 'fas fa-inventory',
                                'tooltip' => __("Stock in locations"),
                            ],
                            'value'      => $orgStock->quantity_in_locations
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
                    ],
                    'locations'       => $locations,
                    'qty_in_location'               => $orgStock->quantity_in_locations,
                    'qty_in_location_fractional'    => riseDivisor(divideWithRemainder(findSmallestFactors($orgStock->quantity_in_locations ?? 0)), $orgStock->packed_in ?? 1),
                ]
            ]
        );
    }


    private function getLatestMovements(OrgStock $orgStock): array
    {
        return $orgStock->orgStockMovements()
            ->whereNot('class', OrgStockMovementClassEnum::GARBAGE)
            ->with(['location', 'user'])
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(function (OrgStockMovement $orgStockMovement) {
                return [
                    'id'                         => $orgStockMovement->id,
                    'date'                       => $orgStockMovement->date,
                    'type_label'                 => $orgStockMovement->type->label(),
                    'class_icon'                 => $orgStockMovement->class->icon(),
                    'quantity'                   => trimDecimalZeros($orgStockMovement->quantity),
                    'is_negative'                => ($orgStockMovement->quantity ?? 0) < 0,
                    'running_quantity_org_stock' => trimDecimalZeros($orgStockMovement->running_quantity_org_stock),
                    'location_code'              => $orgStockMovement->location?->code,
                    'user_name'                  => $orgStockMovement->user?->contact_name,
                    'reason_label'               => $orgStockMovement->reason?->label(),
                ];
            })->toArray();
    }

    private function getDataTradeUnit($tradeUnits): array
    {
        return $tradeUnits->map(function (TradeUnit $tradeUnit) {
            return [
                'slug'   => $tradeUnit->slug,
                'status' => $tradeUnit->status,
                'code'   => $tradeUnit->code,
                'id'     => $tradeUnit->id,
                'stock'  => $tradeUnit->orgStocks->sum('quantity_in_locations'),
                'name'   => $tradeUnit->name,
                'unit'   => $tradeUnit->type,
                'units'  => trimDecimalZeros($tradeUnit->pivot->quantity),
                'images' => $this->getImagesData($tradeUnit),
            ];
        })->toArray();
    }
}
