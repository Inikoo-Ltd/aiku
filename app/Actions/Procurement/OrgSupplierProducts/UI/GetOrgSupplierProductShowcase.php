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
     * The PDF label is printed from an SKO, so the SKOs offered for it are this organisation's linked
     * ones, or for an agent, which holds no stock of its own, the SKOs of the organisations it supplies.
     *
     * Each SKO carries its own numbers, so what is shown is what that SKO's label prints. The master
     * stock is only a fallback when no SKO can be found, and it never knows the unit EAN.
     *
     * @return array{barcodes: array<int, array<string, mixed>>, label_org_stocks: array<int, array{id: int, code: string, organisation_code: string, warehouse_slug: string, barcodes: array<int, array<string, mixed>>}>}
     */
    private function getBarcodesData(OrgSupplierProduct $orgSupplierProduct): array
    {
        $ownOrgStocks = OrgStock::query()
            ->whereHas('orgSupplierProducts', fn ($query) => $query->where('org_supplier_products.id', $orgSupplierProduct->id))
            ->with('organisation.warehouses')
            ->get();

        $labelOrgStocks = ($ownOrgStocks->isNotEmpty() ? $ownOrgStocks : $this->getSuppliedOrgStocks($orgSupplierProduct))
            ->filter(fn (OrgStock $orgStock) => $orgStock->organisation->warehouses->isNotEmpty())
            ->sortBy(fn (OrgStock $orgStock) => $orgStock->organisation->code)
            ->map(fn (OrgStock $orgStock) => [
                'id'                => $orgStock->id,
                'code'              => $orgStock->code,
                'organisation_code' => $orgStock->organisation->code,
                'warehouse_slug'    => $orgStock->organisation->warehouses->first()->slug,
                'barcodes'          => GetOrgStockBarcodes::run($orgStock),
            ])
            ->values()
            ->all();

        $masterStock = $orgSupplierProduct->supplierProduct->stocks->first();

        return [
            'barcodes'         => $labelOrgStocks[0]['barcodes'] ?? ($masterStock ? GetStockBarcodes::run($masterStock) : []),
            'label_org_stocks' => $labelOrgStocks,
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
