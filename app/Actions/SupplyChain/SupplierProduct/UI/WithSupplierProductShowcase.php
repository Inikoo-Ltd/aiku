<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Goods\Stock;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Facades\DB;

trait WithSupplierProductShowcase
{
    /**
     * Staff of an agent only work inside their own organisation, so links to group pages are left out for them.
     */
    private function groupRoute(array $route): ?array
    {
        return request()->route('organisation')?->type === OrganisationTypeEnum::AGENT ? null : $route;
    }

    protected function getSupplierProductShowcase(SupplierProduct $supplierProduct, bool $withSupplyChainLink = false, ?int $organisationId = null): array
    {
        $supplierProduct->loadMissing(['currency', 'supplier', 'agent', 'tradeUnits', 'stocks', 'image', 'images']);

        return [
            'product'     => $this->getSupplierProductDetails($supplierProduct, $withSupplyChainLink),
            'costs'       => $this->getSupplierProductCosts($supplierProduct),
            'packaging'   => $this->getSupplierProductPackaging($supplierProduct),
            'trade_units' => $this->getSupplierProductTradeUnits($supplierProduct),
            'stocks'      => $this->getSupplierProductStocks($supplierProduct),
            'composition' => $this->getSupplierProductComposition($supplierProduct, $organisationId),
            'triangle'    => $this->getSupplierProductTriangle($supplierProduct),
        ];
    }

    /**
     * The composition triangle (TU, SKO, P) of the one stock this supplier product feeds. Units per pack
     * is set in the SKO composition, so the eye cries when the supplier product says otherwise.
     */
    private function getSupplierProductTriangle(SupplierProduct $supplierProduct): ?array
    {
        $tradeUnit = $supplierProduct->tradeUnits->first();
        $stock     = $supplierProduct->stocks->first();
        if (!$tradeUnit || !$stock || $supplierProduct->stocks->count() !== 1) {
            return null;
        }

        $orgStocks     = $stock->orgStocks()->with('organisation:id,code')->get();
        $skoPackedIn   = $supplierProduct->skoPackedIn();
        $sellQuantities = DB::table('model_has_trade_units')
            ->where('model_type', 'Product')
            ->where('trade_unit_id', $tradeUnit->id)
            ->pluck('quantity');
        $distinctSellQuantities = $sellQuantities->map(fn ($quantity) => (float) $quantity)->unique();

        $warning = $skoPackedIn !== null && $supplierProduct->units_per_pack !== null && $skoPackedIn !== $supplierProduct->units_per_pack
            ? __('Units per pack says :supplier but the SKO is packed in :sko. The SKO composition is the one that counts: change it there.', [
                'supplier' => $supplierProduct->units_per_pack,
                'sko'      => $skoPackedIn,
            ])
            : null;

        return [
            'trade_units'       => [
                [
                    'code'             => $tradeUnit->code,
                    'type'             => $tradeUnit->type,
                    'quantity'         => $distinctSellQuantities->count() === 1 ? $distinctSellQuantities->first() : null,
                    'packed_in'        => $skoPackedIn,
                    'packed_in_by_org' => $orgStocks->map(fn (OrgStock $orgStock) => [
                        'org_code'  => $orgStock->organisation->code,
                        'packed_in' => $orgStock->packed_in,
                    ])->values()->all(),
                ],
            ],
            'products_count'    => $sellQuantities->count(),
            'mood'              => $warning ? 'crying' : ($orgStocks->pluck('packed_in')->unique()->count() > 1 ? 'angry' : null),
            'warning'           => $warning,
            'trade_unit'        => [
                'code'  => $tradeUnit->code,
                'route' => $this->groupRoute([
                    'name'       => 'grp.trade_units.units.show',
                    'parameters' => ['tradeUnit' => $tradeUnit->slug],
                ]),
            ],
            'stock'             => [
                'code'  => $stock->code,
                'route' => $this->groupRoute([
                    'name'       => 'grp.goods.stocks.show',
                    'parameters' => ['stock' => $stock->slug],
                ]),
            ],
            'composition_route' => request()->user()?->authTo('goods.edit') ? $this->groupRoute([
                'name'       => 'grp.goods.stocks.composition',
                'parameters' => ['stock' => $stock->slug],
            ]) : null,
        ];
    }

    /**
     * Read-only mirror of the master product composition triangle: this supplier
     * product's trade units, and the org stocks each trade unit is packed into
     * (scoped to one organisation for the org-level showcase).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getSupplierProductComposition(SupplierProduct $supplierProduct, ?int $organisationId = null): array
    {
        return $supplierProduct->tradeUnits->map(function (TradeUnit $tradeUnit) use ($organisationId) {
            $orgStocks = $tradeUnit->orgStocks()
                ->when($organisationId, fn ($query) => $query->where('org_stocks.organisation_id', $organisationId))
                ->with('organisation:id,code,slug')
                ->get();

            return [
                'code'       => $tradeUnit->code,
                'name'       => $tradeUnit->name,
                'slug'       => $tradeUnit->slug,
                'quantity'   => (float) $tradeUnit->pivot->quantity,
                'route'      => $this->groupRoute([
                    'name'       => 'grp.goods.trade-units.show',
                    'parameters' => ['tradeUnit' => $tradeUnit->slug],
                ]),
                'org_stocks' => $orgStocks->map(fn (OrgStock $orgStock) => [
                    'code'         => $orgStock->code,
                    'slug'         => $orgStock->slug,
                    'quantity'     => (float) $orgStock->quantity_in_locations,
                    'organisation' => [
                        'code' => $orgStock->organisation->code,
                        'slug' => $orgStock->organisation->slug,
                    ],
                    'route'        => $this->groupRoute([
                        'name'       => 'grp.majordomo.redirect_org_stock',
                        'parameters' => ['orgStock' => $orgStock->id],
                    ]),
                ])->all(),
            ];
        })->all();
    }

    /**
     * Internal pictures (warehouse, packing) kept on the supplier product, never on its trade units, so they never reach websites.
     *
     * @return array{upload_route: array<string, mixed>|null, images: array<int, array<string, mixed>>}
     */
    protected function getSupplierProductInternalImages(SupplierProduct $supplierProduct, bool $canEdit, ?OrgSupplierProduct $orgSupplierProduct = null): array
    {
        [$routePrefix, $routeParameters] = $orgSupplierProduct
            ? ['grp.models.org_supplier_product', ['orgSupplierProduct' => $orgSupplierProduct->id]]
            : ['grp.models.supplier-product', ['supplierProduct' => $supplierProduct->id]];

        return [
            'upload_route' => $canEdit ? [
                'name'       => $routePrefix.'.upload_images',
                'parameters' => $routeParameters,
                'method'     => 'post',
            ] : null,
            'images'       => $supplierProduct->images->map(fn (Media $media) => [
                'id'           => $media->id,
                'is_main'      => $media->id === $supplierProduct->image_id,
                'image'        => GetPictureSources::run($media->getImage()->resize(400, 400)),
                'original_url' => route('grp.media.download', ['media' => $media->ulid, 'inline' => true]),
                'download_url' => route('grp.media.download', ['media' => $media->ulid]),
                'detach_route' => $canEdit ? [
                    'name'       => $routePrefix.'.detach_image',
                    'parameters' => array_merge($routeParameters, ['media' => $media->id]),
                    'method'     => 'delete',
                ] : null,
            ])->values()->all(),
        ];
    }

    private function getSupplierProductDetails(SupplierProduct $supplierProduct, bool $withSupplyChainLink): array
    {
        $imageMedia = $supplierProduct->image ?? $supplierProduct->tradeUnits->first()?->image;

        return [
            'code'         => $supplierProduct->code,
            'name'         => $supplierProduct->name,
            'description'  => $supplierProduct->description,
            'image'        => $supplierProduct->imageSources() ?? $supplierProduct->tradeUnits->first()?->imageSources(),
            'original_url' => $imageMedia ? route('grp.media.download', ['media' => $imageMedia->ulid, 'inline' => true]) : null,
            'download_url' => $imageMedia ? route('grp.media.download', ['media' => $imageMedia->ulid]) : null,
            'state'        => [
                'label' => SupplierProductStateEnum::labels()[$supplierProduct->state->value],
                'icon'  => SupplierProductStateEnum::stateIcon()[$supplierProduct->state->value],
            ],
            'is_available' => $supplierProduct->is_available,
            'composition'  => $supplierProduct->trade_unit_composition?->value,
            'route'        => $withSupplyChainLink && $supplierProduct->supplier && $this->groupRoute([]) !== null ? [
                'name'       => 'grp.supply-chain.suppliers.supplier_products.show',
                'parameters' => [
                    'supplier'        => $supplierProduct->supplier->slug,
                    'supplierProduct' => $supplierProduct->slug,
                ],
            ] : null,
        ];
    }

    private function getSupplierProductCosts(SupplierProduct $supplierProduct): array
    {
        $unitCost      = (float) $supplierProduct->cost;
        $extraCosts    = (float) $supplierProduct->extra_costs;
        $deliveredCost = $unitCost * (1 + $extraCosts);

        return [
            'currency_code'          => $supplierProduct->currency?->code,
            'unit_cost'              => $unitCost,
            'extra_costs_percentage' => $extraCosts * 100,
            'delivered_unit_cost'    => $deliveredCost,
            'pack_cost'              => $supplierProduct->units_per_pack ? $deliveredCost * $supplierProduct->units_per_pack : null,
            'carton_cost'            => $supplierProduct->units_per_carton ? $deliveredCost * $supplierProduct->units_per_carton : null,
        ];
    }

    private function getSupplierProductPackaging(SupplierProduct $supplierProduct): array
    {
        return [
            'units_per_pack'   => $supplierProduct->units_per_pack,
            'units_per_carton' => $supplierProduct->units_per_carton,
            'cbm'              => $supplierProduct->cbm ? (float) $supplierProduct->cbm : null,
        ];
    }

    private function getSupplierProductTradeUnits(SupplierProduct $supplierProduct): array
    {
        return $supplierProduct->tradeUnits->map(function (TradeUnit $tradeUnit) {
            return [
                'slug'  => $tradeUnit->slug,
                'code'  => $tradeUnit->code,
                'name'  => $tradeUnit->name,
                'unit'  => $tradeUnit->type,
                'units' => trimDecimalZeros($tradeUnit->pivot->quantity),
                'image' => $tradeUnit->imageSources(),
                'route' => $this->groupRoute([
                    'name'       => 'grp.trade_units.units.show',
                    'parameters' => ['tradeUnit' => $tradeUnit->slug],
                ]),
            ];
        })->all();
    }

    private function getSupplierProductStocks(SupplierProduct $supplierProduct): array
    {
        return $supplierProduct->stocks->map(function (Stock $stock) {
            return [
                'slug'  => $stock->slug,
                'code'  => $stock->code,
                'name'  => $stock->name,
                'route' => $this->groupRoute([
                    'name'       => 'grp.goods.stocks.show',
                    'parameters' => ['stock' => $stock->slug],
                ]),
            ];
        })->all();
    }

    /**
     * The SKO barcode is what the warehouse scans, so it is changed only by whoever may edit stock in the
     * organisation that holds the SKO, through the same update as its SKO page, whichever organisation's
     * supplier product page it is reached from.
     *
     * @return array{name: string, parameters: array<string, string>, method: string}|null
     */
    protected function getSkoBarcodeUpdateRoute(OrgStock $orgStock): ?array
    {
        $warehouse = $orgStock->organisation->warehouses->first();

        if (!$warehouse || !request()->user()?->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($orgStock->organisation))) {
            return null;
        }

        return [
            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.update',
            'parameters' => [
                'organisation' => $orgStock->organisation->slug,
                'warehouse'    => $warehouse->slug,
                'orgStock'     => $orgStock->slug,
            ],
            'method'     => 'patch',
        ];
    }

    protected function getSupplierParty(?Supplier $supplier): ?array
    {
        if (!$supplier) {
            return null;
        }

        return [
            'label' => __('Supplier'),
            'icon'  => 'fal fa-person-dolly',
            'name'  => $supplier->name,
            'code'  => $supplier->code,
            'image' => $supplier->imageSources(),
            'route' => $this->groupRoute([
                'name'       => 'grp.supply-chain.suppliers.show',
                'parameters' => ['supplier' => $supplier->slug],
            ]),
        ];
    }

    protected function getAgentParty(?Agent $agent): ?array
    {
        if (!$agent) {
            return null;
        }

        return [
            'label' => __('Agent'),
            'icon'  => 'fal fa-people-arrows',
            'name'  => $agent->organisation->name,
            'code'  => $agent->organisation->code,
            'image' => $agent->imageSources(),
            'route' => $this->groupRoute([
                'name'       => 'grp.supply-chain.agents.show',
                'parameters' => ['agent' => $agent->slug],
            ]),
        ];
    }

    protected function getOrgSupplierParty(?OrgSupplier $orgSupplier): ?array
    {
        if (!$orgSupplier || !$orgSupplier->supplier) {
            return null;
        }

        return [
            'label' => __('Supplier'),
            'icon'  => 'fal fa-person-dolly',
            'name'  => $orgSupplier->supplier->name,
            'code'  => $orgSupplier->supplier->code,
            'image' => $orgSupplier->supplier->imageSources(),
            'route' => [
                'name'       => 'grp.org.procurement.org_suppliers.show',
                'parameters' => [
                    'organisation' => (request()->route('organisation') ?? $orgSupplier->organisation)->slug,
                    'orgSupplier'  => $orgSupplier->slug,
                ],
            ],
        ];
    }

    protected function getOrgAgentParty(?OrgAgent $orgAgent): ?array
    {
        if (!$orgAgent || !$orgAgent->agent) {
            return null;
        }

        return [
            'label' => __('Agent'),
            'icon'  => 'fal fa-people-arrows',
            'name'  => $orgAgent->agent->organisation->name,
            'code'  => $orgAgent->agent->organisation->code,
            'image' => $orgAgent->agent->imageSources(),
            'route' => [
                'name'       => 'grp.org.procurement.org_agents.show',
                'parameters' => [
                    'organisation' => (request()->route('organisation') ?? $orgAgent->organisation)->slug,
                    'orgAgent'     => $orgAgent->slug,
                ],
            ],
        ];
    }

    protected function getProcurementStatsBoxes(?object $stats): array
    {
        if (!$stats) {
            return [];
        }

        return [
            [
                'label'       => __('Purchase Orders'),
                'count'       => $stats->number_purchase_orders,
                'description' => __('open').': '.$stats->number_open_purchase_orders,
            ],
            [
                'label' => __('Deliveries'),
                'count' => $stats->number_stock_deliveries,
            ],
            [
                'label' => __('Ordered Items'),
                'count' => $stats->number_purchase_order_transactions,
            ],
            [
                'label' => __('Delivered Items'),
                'count' => $stats->number_stock_delivery_items,
            ],
        ];
    }
}
