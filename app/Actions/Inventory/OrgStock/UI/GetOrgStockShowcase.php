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
use Illuminate\Support\Facades\DB;
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
                'barcode_update_route' => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.update',
                    'parameters' => [
                        'organisation' => $warehouse->organisation->slug,
                        'warehouse'    => $warehouse->slug,
                        'orgStock'     => $orgStock->slug,
                    ],
                    'method'     => 'patch',
                ],
                'can_edit_unit_barcode' => request()->user()?->authTo("supervisor-stocks.{$warehouse->id}") ?? false,
                'unit_barcode_update_route' => [
                    'name'       => 'grp.org.warehouses.show.inventory.org_stocks.update_unit_barcode',
                    'parameters' => [
                        'organisation' => $warehouse->organisation->slug,
                        'warehouse'    => $warehouse->slug,
                        'orgStock'     => $orgStock->slug,
                    ],
                    'method'     => 'patch',
                ],
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
                    'stock_cost'      => $this->getStockCost($orgStock),
                    'summary'         => [
                        'quantity_in_locations' => [
                            'icon_state' => [
                                'icon'    => 'fas fa-inventory',
                                'tooltip' => __("Stock in locations"),
                            ],
                            'value'            => $orgStock->quantity_in_locations,
                            'value_fractional' => $this->getFractionalQuantity($orgStock->quantity_in_locations, $orgStock->packed_in),
                        ],
                        'quantity_in_submitted_orders' => [
                            'icon_state' => [
                                'icon'    => 'fas fa-shopping-cart',
                                'tooltip' => __("Reserved paid parts in process by customer services"),
                            ],
                            'value'            => $orgStock->quantity_in_submitted_orders,
                            'value_fractional' => $this->getFractionalQuantity($orgStock->quantity_in_submitted_orders, $orgStock->packed_in),
                        ],
                        'quantity_to_be_picked'        => [
                            'icon_state' => [
                                'icon'    => 'fas fa-shopping-basket',
                                'tooltip' => __("Parts been picked"),
                            ],
                            'value'            => $orgStock->quantity_to_be_picked,
                            'value_fractional' => $this->getFractionalQuantity($orgStock->quantity_to_be_picked, $orgStock->packed_in),
                        ],
                    ],
                    'locations'       => $locations,
                    'qty_in_location'               => $orgStock->quantity_in_locations,
                    'qty_in_location_fractional'    => $this->getFractionalQuantity($orgStock->quantity_in_locations, $orgStock->packed_in),
                ]
            ]
        );
    }


    /**
     * @return array{0: int|float, 1: array{0: int|float, 1: int|float}}
     */
    /**
     * @return array{sku_value: ?float, total_stock_value: float, current_supplier_sku_cost: ?float, fifo_per_sku: ?float, wac_per_sku: ?float, lpp_per_sku: ?float}
     */
    private function getStockCost(OrgStock $orgStock): array
    {
        $latestHistory = DB::table('org_stock_histories')
            ->where('org_stock_id', $orgStock->id)
            ->orderByDesc('date')
            ->first(['fifo_per_sku', 'wac_per_sku', 'lpp_per_sku']);

        return [
            'sku_value'                 => $orgStock->sku_value,
            'total_stock_value'         => $orgStock->sku_value * $orgStock->quantity_available,
            'current_supplier_sku_cost' => $orgStock->current_supplier_sku_cost,
            'fifo_per_sku'              => $latestHistory?->fifo_per_sku,
            'wac_per_sku'               => $latestHistory?->wac_per_sku,
            'lpp_per_sku'               => $latestHistory?->lpp_per_sku,
        ];
    }

    private function getFractionalQuantity(int|float|null $quantity, int|float|null $packedIn): array
    {
        return riseDivisor(divideWithRemainder(findSmallestFactors($quantity ?? 0)), $packedIn ?? 1);
    }

    private function getLatestMovements(OrgStock $orgStock): array
    {
        return $orgStock->orgStockMovements()
            ->whereNot('class', OrgStockMovementClassEnum::GARBAGE)
            ->with(['location', 'user'])
            ->orderByDesc('date')
            ->limit(5)
            ->get()
            ->map(function (OrgStockMovement $orgStockMovement) use ($orgStock) {
                return [
                    'id'                                    => $orgStockMovement->id,
                    'date'                                  => $orgStockMovement->date,
                    'type_label'                            => $orgStockMovement->type->label(),
                    'class_icon'                            => $orgStockMovement->class->icon(),
                    'quantity'                              => trimDecimalZeros($orgStockMovement->quantity),
                    'quantity_fractional'                   => $this->getFractionalQuantity(abs($orgStockMovement->quantity ?? 0), $orgStock->packed_in),
                    'is_negative'                           => ($orgStockMovement->quantity ?? 0) < 0,
                    'running_quantity_org_stock'            => trimDecimalZeros($orgStockMovement->running_quantity_org_stock),
                    'running_quantity_org_stock_fractional' => $this->getFractionalQuantity(abs($orgStockMovement->running_quantity_org_stock ?? 0), $orgStock->packed_in),
                    'is_running_negative'                   => ($orgStockMovement->running_quantity_org_stock ?? 0) < 0,
                    'location_code'                         => $orgStockMovement->location?->code,
                    'user_name'                             => $orgStockMovement->user?->contact_name,
                    'reason_label'                          => $orgStockMovement->reason?->label(),
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
