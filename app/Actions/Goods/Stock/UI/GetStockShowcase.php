<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Aug 2024 17:07:40 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\Traits\HasBucketImages;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

class GetStockShowcase
{
    use AsObject;
    use HasBucketImages;

    public function handle(Stock $stock): array
    {
        $numberLocations   = 0;
        $quantityLocations = 0;
        foreach ($stock->orgStocks as $orgStock) {
            $num               = $orgStock->locationOrgStocks()->count();
            $quantity          = $orgStock->quantity_in_locations;
            $quantityLocations = $quantityLocations   + $quantity;
            $numberLocations   = $numberLocations     + $num;
        }

        return [
            'trade_units'   => $stock->tradeUnits->map(fn (TradeUnit $tradeUnit) => [
                'id'     => $tradeUnit->id,
                'slug'   => $tradeUnit->slug,
                'code'   => $tradeUnit->code,
                'name'   => $tradeUnit->name,
                'unit'   => $tradeUnit->type,
                'units'  => trimDecimalZeros($tradeUnit->pivot->quantity),
                'images' => $this->getImagesData($tradeUnit),
            ])->all(),
            'currency_code' => $stock->group->currency->code,
            'sales_data'    => GetStockTimeSeriesData::run($stock),
            'org_stocks'    => $this->getOrgStocksData($stock),
             'contactCard' => [
                 'id'                 => $stock->id,
                 'slug'               => $stock->slug,
                 'code'               => $stock->slug,
                 'unit_value'         => $stock->unit_value,
                 'description'        => $stock->description,
                 'number_locations'   => $numberLocations,
                 'quantity_locations' => $quantityLocations,
                 'photo'              => $stock->imageSources(),
                //  'locations'          => LocationOrgStocksResource::collection($stock->orgStocks->first()->locationOrgStocks)
             ],
            'locationRoute'            => [
                'name'       => 'grp.org.warehouses.show.infrastructure.locations.index',
                'parameters' => [
                    'organisation' => null,
                    'warehouse'    => null
                ]
            ],
            'associateLocationRoute'  => [
                'method'     => 'post',
                'name'       => 'grp.models.org_stock.location.store',
                'parameters' => [
                    'orgStock' => null
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
        ];
    }

    /**
     * @return array{summary: array<string, array{icon_state: array{icon: string, tooltip: string}, value: float}>, quantity_in_locations: float, items: array<int, array<string, mixed>>}
     */
    private function getOrgStocksData(Stock $stock): array
    {
        $orgStocks = $stock->orgStocks()->with('organisation')->get()->sortBy('organisation.code')->values();

        return [
            'summary'               => [
                'quantity_in_locations'        => [
                    'icon_state' => ['icon' => 'fas fa-inventory', 'tooltip' => __('Stock in locations')],
                    'value'      => (float)$orgStocks->sum('quantity_in_locations'),
                ],
                'quantity_in_submitted_orders' => [
                    'icon_state' => ['icon' => 'fas fa-shopping-cart', 'tooltip' => __('Reserved paid parts in process by customer services')],
                    'value'      => (float)$orgStocks->sum('quantity_in_submitted_orders'),
                ],
                'quantity_to_be_picked'        => [
                    'icon_state' => ['icon' => 'fas fa-shopping-basket', 'tooltip' => __('Parts been picked')],
                    'value'      => (float)$orgStocks->sum('quantity_to_be_picked'),
                ],
            ],
            'quantity_in_locations' => (float)$orgStocks->sum('quantity_in_locations'),
            'items'                 => $orgStocks->map(fn (OrgStock $orgStock) => [
                'id'                    => $orgStock->id,
                'code'                  => $orgStock->code,
                'organisation_code'     => $orgStock->organisation->code,
                'organisation_name'     => $orgStock->organisation->name,
                'quantity_in_locations' => (float)$orgStock->quantity_in_locations,
                'route'                 => [
                    'name'       => 'grp.majordomo.redirect_org_stock',
                    'parameters' => [$orgStock->id],
                ],
            ])->all(),
        ];
    }
}
