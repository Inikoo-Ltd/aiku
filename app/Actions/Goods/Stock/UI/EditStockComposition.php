<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Stock\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\Stock;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * The OS-TU leg of the composition triangle: how this SKU is packed from trade units,
 * shown against every product that sells them, with the delivery note impact inline.
 */
class EditStockComposition extends OrgAction
{
    use WithGoodsEditAuthorisation;

    public function handle(Stock $stock): Stock
    {
        return $stock;
    }

    public function asController(Stock $stock, ActionRequest $request): Stock
    {
        $this->initialisationFromGroup($stock->group, $request);

        return $this->handle($stock);
    }

    public function htmlResponse(Stock $stock, ActionRequest $request): Response
    {
        return Inertia::render(
            'Goods/ProductComposition',
            [
                'title'       => __('Composition').': '.$stock->code,
                'breadcrumbs' => $this->getBreadcrumbs($stock),
                'pageHead'    => [
                    'model'   => __('Composition & packing'),
                    'title'   => $stock->code,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-atom'],
                        'title' => __('Composition'),
                    ],
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.goods.stocks.show',
                                'parameters' => [
                                    'stock' => $stock->slug,
                                ]
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => $this->getBlueprint($stock),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stock.update',
                            'parameters' => [
                                'stock' => $stock->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBlueprint(Stock $stock): array
    {
        return [
            [
                'label'  => __('Trade units'),
                'icon'   => 'fa-light fa-atom',
                'fields' => [
                    'trade_units' => [
                        'label'       => __('Trade units'),
                        'type'        => 'trade-units-for-stock',
                        'fetchRoute'  => [
                            'name' => 'grp.json.master_product_category.all_trade_units',
                        ],
                        'impactRoute' => [
                            'name'       => 'grp.json.stock.trade-unit-changes-impact',
                            'parameters' => [
                                'stock' => $stock->slug
                            ]
                        ],
                        'value'       => $stock->tradeUnits->map(fn ($tradeUnit) => [
                            'id'       => $tradeUnit->id,
                            'code'     => $tradeUnit->code,
                            'name'     => $tradeUnit->name,
                            'quantity' => $tradeUnit->pivot->quantity,
                        ])->values(),

                        /*
                         * The other leg of the triangle: which products sell these trade
                         * units and at what pack size, so whoever edits how the SKU is
                         * packed can judge whether the SKU or the product is the wrong one.
                         */
                        'productsContext' => $this->getProductsContext($stock),
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<int, array{code: string, shop_code: string, quantity: float}>> keyed by trade unit id
     */
    public function getProductsContext(Stock $stock): array
    {
        return DB::table('model_has_trade_units')
            ->join('products', 'products.id', '=', 'model_has_trade_units.model_id')
            ->join('shops', 'shops.id', '=', 'products.shop_id')
            ->where('model_has_trade_units.model_type', 'Product')
            ->whereIn('model_has_trade_units.trade_unit_id', $stock->tradeUnits->pluck('id'))
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

    public function getBreadcrumbs(Stock $stock): array
    {
        return ShowStock::make()->getBreadcrumbs(
            $stock,
            'grp.goods.stocks.show',
            ['stock' => $stock->slug],
            suffix: '('.__('Composition').')'
        );
    }
}
