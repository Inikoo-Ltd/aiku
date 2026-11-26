<?php

/*
 * author Arya Permana - Kirin
 * created on 20-02-2025-11h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Actions\Goods\Stock\UI\GetStockBarcodes;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes;
use App\Actions\Procurement\OrgSupplierProducts\UI\IndexAgentSupplierProductSuppliers;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\SupplyChain\Agent;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsObject;

class GetSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;
    use WithSupplierProductInfo;

    public function handle(SupplierProduct $supplierProduct): array
    {
        return array_merge(
            $this->getSupplierProductShowcase($supplierProduct),
            [
                'parties' => array_values(array_filter([
                    $this->getSupplierParty($supplierProduct->supplier),
                    $this->getAgentParty($supplierProduct->agent),
                ])),
                'other_suppliers' => $this->otherSuppliers($supplierProduct),
                'stats'   => $this->getProcurementStatsBoxes($supplierProduct->stats),
                'supplierProductInfo' => $this->supplierProductInfo($supplierProduct),
                'internal_images'     => $this->getSupplierProductInternalImages($supplierProduct, (bool) request()->user()?->authTo(['supply-chain.edit', 'goods.edit'])),
                'carton'              => [
                    'supplier_product_id' => $supplierProduct->id,
                    'net_weight'          => $supplierProduct->carton_net_weight,
                    'gross_weight'        => $supplierProduct->carton_weight,
                    'update_route'        => null,
                ],
            ],
            $this->getBarcodesData($supplierProduct)
        );
    }

    /**
     * Every other supplier selling the same SKU, linked to its product page, staying inside the agent
     * when the page was opened from an agent and the other supplier belongs to it. A supplier outside this
     * product's agent carries its country, so the box can show where it is.
     *
     * @return array<int, array{id: int, supplier_code: string, supplier_name: string, image: array<string, mixed>|null, code: string, is_available: bool, other_agent: array{name: string|null, country_code: string|null}|null, supplier_route: array<string, mixed>, route: array<string, mixed>}>
     */
    private function otherSuppliers(SupplierProduct $supplierProduct): array
    {
        $viewingAgent = request()->route('agent');
        $agentId      = $supplierProduct->supplier?->agent_id;

        return SupplierProduct::query()
            ->join('suppliers', 'suppliers.id', 'supplier_products.supplier_id')
            ->leftJoin('addresses', 'addresses.id', 'suppliers.address_id')
            ->leftJoin('agents', 'agents.id', 'suppliers.agent_id')
            ->leftJoin('organisations as agent_organisations', 'agent_organisations.id', 'agents.organisation_id')
            ->where('supplier_products.id', '!=', $supplierProduct->id)
            ->whereIn('supplier_products.id', StockHasSupplierProduct::whereIn('stock_id', IndexAgentSupplierProductSuppliers::stockIds($supplierProduct))->select('supplier_product_id'))
            ->orderBy('suppliers.code')
            ->with('supplier.image')
            ->get([
                'supplier_products.id',
                'supplier_products.slug',
                'supplier_products.code',
                'supplier_products.is_available',
                'supplier_products.supplier_id',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'suppliers.agent_id as supplier_agent_id',
                'addresses.country_code as supplier_country_code',
                'agent_organisations.name as supplier_agent_name',
            ])
            ->map(fn (SupplierProduct $other) => [
                'id'             => $other->id,
                'supplier_code'  => $other->supplier_code,
                'supplier_name'  => $other->supplier_name,
                'image'          => $other->supplier?->imageSources(),
                'code'           => $other->code,
                'is_available'   => (bool) $other->is_available,
                'other_agent'    => $other->supplier_agent_id === $agentId ? null : [
                    'name'         => $other->supplier_agent_name,
                    'country_code' => $other->supplier_country_code,
                ],
                'supplier_route' => [
                    'name'       => 'grp.supply-chain.suppliers.show',
                    'parameters' => ['supplier' => $other->supplier_slug],
                ],
                'route'          => $viewingAgent instanceof Agent && $viewingAgent->id === $other->supplier_agent_id
                    ? ['name' => 'grp.supply-chain.agents.show.supplier_products.show', 'parameters' => ['agent' => $viewingAgent->slug, 'supplierProduct' => $other->slug]]
                    : ['name' => 'grp.supply-chain.supplier_products.show', 'parameters' => ['supplierProduct' => $other->slug]],
            ])
            ->values()
            ->all();
    }

    /**
     * The group page belongs to no organisation, so it shows the SKO of every organisation that holds
     * this product's stock, each with the barcodes its label prints. The carton barcode is saved
     * through the supplier product of that SKO's organisation, when the user may edit its procurement.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array<string, mixed>>, selected_label_org_stock_id: int|null}
     */
    private function getBarcodesData(SupplierProduct $supplierProduct): array
    {
        $orgSupplierProducts = $supplierProduct->orgSupplierProducts()->get()->keyBy('organisation_id');

        $labelOrgStocks = OrgStock::query()
            ->whereIn('stock_id', $supplierProduct->stocks->pluck('id'))
            ->with('organisation.warehouses')
            ->get()
            ->filter(fn (OrgStock $orgStock) => $orgStock->organisation->warehouses->isNotEmpty())
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->map(fn (OrgStock $orgStock) => [
                'id'                          => $orgStock->id,
                'code'                        => $orgStock->code,
                'organisation_id'             => $orgStock->organisation_id,
                'organisation_code'           => $orgStock->organisation->code,
                'barcodes'                    => GetOrgStockBarcodes::run($orgStock, $supplierProduct->units_per_carton),
                'carton_barcode_update_route' => $this->getCartonBarcodeUpdateRoute($orgStock, $orgSupplierProducts->get($orgStock->organisation_id)),
                'sko_barcode_update_route'    => $this->getSkoBarcodeUpdateRoute($orgStock),
                'label_options_route'         => [
                    'name'       => 'grp.json.warehouse.org_stock.label_options',
                    'parameters' => [
                        'warehouse' => $orgStock->organisation->warehouses->first()->slug,
                        'orgStock'  => $orgStock->id,
                    ],
                ],
            ])
            ->values()
            ->all();

        $masterStock = $supplierProduct->stocks->first();

        return [
            'barcodes'                    => $labelOrgStocks[0]['barcodes'] ?? ($masterStock ? GetStockBarcodes::run($masterStock, $supplierProduct->units_per_carton) : GetSupplierProductBarcodes::run($supplierProduct)),
            'label_org_stocks'            => $labelOrgStocks,
            'selected_label_org_stock_id' => $labelOrgStocks[0]['id'] ?? null,
        ];
    }

    private function getCartonBarcodeUpdateRoute(OrgStock $orgStock, ?OrgSupplierProduct $orgSupplierProduct): ?array
    {
        if (!$orgSupplierProduct || !$orgStock->stock_id || !request()->user()?->authTo("procurement.{$orgStock->organisation_id}.edit")) {
            return null;
        }

        return [
            'name'       => 'grp.models.org.org_supplier_product.carton_barcode.update',
            'parameters' => [
                'organisation'       => $orgStock->organisation_id,
                'orgSupplierProduct' => $orgSupplierProduct->id,
                'stock'              => $orgStock->stock_id,
            ],
        ];
    }
}
