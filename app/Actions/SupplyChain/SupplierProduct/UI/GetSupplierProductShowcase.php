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
use App\Models\Inventory\OrgStock;
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
     * The group page belongs to no organisation, so it shows the SKO of every organisation that holds
     * this product's stock, each with the barcodes its label prints. The carton is edited from an
     * organisation's supplier product page, so here it is read only.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array<string, mixed>>, selected_label_org_stock_id: int|null}
     */
    private function getBarcodesData(SupplierProduct $supplierProduct): array
    {
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
                'carton_barcode_update_route' => null,
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
}
