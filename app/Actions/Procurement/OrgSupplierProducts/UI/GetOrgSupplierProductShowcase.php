<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Procurement\OrgSupplierProducts\UI;

use App\Actions\Goods\Stock\UI\GetStockBarcodes;
use App\Actions\Inventory\OrgStock\UI\GetOrgStockBarcodes;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductInfo;
use App\Actions\SupplyChain\SupplierProduct\UI\WithSupplierProductShowcase;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgSupplierProductShowcase
{
    use AsObject;
    use WithSupplierProductShowcase;
    use WithSupplierProductInfo;

    public function handle(OrgSupplierProduct $orgSupplierProduct): array
    {
        return array_merge(
            $this->getSupplierProductShowcase($orgSupplierProduct->supplierProduct, withSupplyChainLink: true, organisationId: $orgSupplierProduct->organisation_id),
            [
                'organisation' => [
                    'name'         => $orgSupplierProduct->organisation->name,
                    'code'         => $orgSupplierProduct->organisation->code,
                    'state'        => $orgSupplierProduct->state,
                    'is_available' => $orgSupplierProduct->is_available,
                ],
                'parties'      => array_values(array_filter([
                    $this->getOrgSupplierParty($orgSupplierProduct->orgSupplier),
                    $this->getOrgAgentParty($orgSupplierProduct->orgAgent),
                ])),
                'stats'        => $this->getProcurementStatsBoxes($orgSupplierProduct->stats),
                'supplierProductInfo' => $this->supplierProductInfo($orgSupplierProduct->supplierProduct),
            ],
            $this->getBarcodesData($orgSupplierProduct)
        );
    }

    /**
     * The numbers come from this organisation's SKO when the supplier product is linked to one, and
     * from the master stock otherwise, which is always the case in an agent organisation since it
     * holds no stock of its own.
     *
     * The PDF label is printed from an SKO, so the SKOs offered for it are this organisation's linked
     * ones, or for an agent the SKOs of the organisations it supplies.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array{id: int, code: string, organisation_code: string, warehouse_slug: string}>}
     */
    private function getBarcodesData(OrgSupplierProduct $orgSupplierProduct): array
    {
        $ownOrgStocks = OrgStock::query()
            ->whereHas('orgSupplierProducts', fn ($query) => $query->where('org_supplier_products.id', $orgSupplierProduct->id))
            ->with('organisation.warehouses')
            ->get();

        $masterStock = $orgSupplierProduct->supplierProduct->stocks->first();

        if ($ownOrgStocks->count() === 1) {
            $barcodes = GetOrgStockBarcodes::run($ownOrgStocks->first());
        } elseif ($masterStock) {
            $barcodes = GetStockBarcodes::run($masterStock);
        } else {
            $barcodes = [];
        }

        $labelOrgStocks = $ownOrgStocks->isNotEmpty()
            ? $ownOrgStocks
            : $this->getSuppliedOrgStocks($orgSupplierProduct);

        return [
            'barcodes'         => $barcodes,
            'label_org_stocks' => $labelOrgStocks
                ->filter(fn (OrgStock $orgStock) => $orgStock->organisation->warehouses->isNotEmpty())
                ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
                ->map(fn (OrgStock $orgStock) => [
                    'id'                => $orgStock->id,
                    'code'              => $orgStock->code,
                    'organisation_code' => $orgStock->organisation->code,
                    'warehouse_slug'    => $orgStock->organisation->warehouses->first()->slug,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, OrgStock>
     */
    private function getSuppliedOrgStocks(OrgSupplierProduct $orgSupplierProduct): Collection
    {
        $stockIds = $orgSupplierProduct->supplierProduct->stocks->pluck('id');

        if ($stockIds->isEmpty()) {
            return collect();
        }

        return OrgStock::query()
            ->whereIn('stock_id', $stockIds)
            ->with('organisation.warehouses')
            ->get();
    }
}
