<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithInventoryAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * How THIS warehouse packs the SKU: the org stock's own OS-TU pivot, edited without
 * touching any other organisation. The group SKU composition page mirrors to all orgs;
 * this page is the per-warehouse override, because the org stock maps physical reality.
 */
class EditOrgStockComposition extends OrgAction
{
    use WithInventoryAuthorisation;

    public function handle(OrgStock $orgStock): OrgStock
    {
        return $orgStock;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock);
    }

    public function htmlResponse(OrgStock $orgStock, ActionRequest $request): Response
    {
        $showRoute = [
            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.current_org_stocks.show',
            'parameters' => [
                'organisation' => $orgStock->organisation->slug,
                'warehouse'    => $this->warehouse->slug,
                'orgStock'     => $orgStock->slug,
            ]
        ];

        return Inertia::render(
            'Goods/ProductComposition',
            [
                'title'       => __('Packing').': '.$orgStock->code,
                'breadcrumbs' => ShowOrgStock::make()->getBreadcrumbs(
                    orgStock: $orgStock,
                    routeName: $showRoute['name'],
                    routeParameters: $showRoute['parameters'],
                    suffix: '('.__('Packing').')'
                ),
                'pageHead'    => [
                    'model'   => __('Warehouse packing'),
                    'title'   => $orgStock->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Packing'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => $showRoute
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => $this->getBlueprint($orgStock),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.org_stock.trade_units.update',
                            'parameters' => [
                                'orgStock' => $orgStock->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBlueprint(OrgStock $orgStock): array
    {
        return [
            [
                'label'  => __('Packed from trade units in :org', ['org' => $orgStock->organisation->code]),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'      => __('Trade units'),
                        'type'       => 'trade-units-for-stock',
                        'fetchRoute' => [
                            'name' => 'grp.json.master_product_category.all_trade_units',
                        ],
                        'value'      => $orgStock->tradeUnits->map(fn ($tradeUnit) => [
                            'id'       => $tradeUnit->id,
                            'code'     => $tradeUnit->code,
                            'name'     => $tradeUnit->name,
                            'quantity' => $tradeUnit->pivot->quantity,
                        ])->values(),

                        /*
                         * Only this organisation's products: this page changes one
                         * warehouse's picking, the context should match its blast radius.
                         */
                        'productsContext' => $this->getProductsContext($orgStock),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<int, array{code: string, shop_code: string, quantity: float}>> keyed by trade unit id
     */
    public function getProductsContext(OrgStock $orgStock): array
    {
        return DB::table('model_has_trade_units')
            ->join('products', 'products.id', '=', 'model_has_trade_units.model_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('model_has_trade_units.model_type', 'Product')
            ->whereIn('model_has_trade_units.trade_unit_id', $orgStock->tradeUnits->pluck('id'))
            ->where('products.organisation_id', $orgStock->organisation_id)
            ->whereNull('products.deleted_at')
            ->where('products.is_for_sale', true)
            ->select([
                'model_has_trade_units.trade_unit_id',
                'products.code',
                'shops.code as shop_code',
                'model_has_trade_units.quantity',
            ])
            ->orderBy('products.code')
            ->get()
            ->groupBy('trade_unit_id')
            ->map(fn ($products) => $products->map(fn ($product) => [
                'code'      => $product->code,
                'shop_code' => $product->shop_code,
                'quantity'  => (float) $product->quantity,
            ])->values()->all())
            ->toArray();
    }
}
