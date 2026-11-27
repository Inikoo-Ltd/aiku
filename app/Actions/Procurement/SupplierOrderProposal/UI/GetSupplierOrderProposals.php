<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-27 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\SupplierOrderProposal\UI;

use App\Actions\Procurement\OrgSupplier\GetSupplierStockCoverBuckets;
use App\Enums\Procurement\SupplierOrderProposal\SupplierOrderProposalStateEnum;
use App\Models\Procurement\SupplierOrderProposal;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The lines suppliers proposed from their portal that wait for a buyer of the organisation, for the Shopping page of
 * a supplier or of an agent.
 */
class GetSupplierOrderProposals
{
    use AsObject;

    /**
     * @param  Collection<int, int>|array<int, int>  $orgSupplierIds
     *
     * @return list<array<string, mixed>>
     */
    public function handle(int $organisationId, Collection|array $orgSupplierIds): array
    {
        return SupplierOrderProposal::where('organisation_id', $organisationId)
            ->whereIn('org_supplier_id', $orgSupplierIds)
            ->where('state', SupplierOrderProposalStateEnum::PENDING)
            ->with(['supplier.currency', 'supplierProduct.image', 'supplierProduct.tradeUnits.image', 'supplierPortalContact'])
            ->oldest('id')
            ->get()
            ->map(function (SupplierOrderProposal $proposal) {
                $product    = $proposal->supplierProduct;
                $imageOwner = $product?->image_id ? $product : $product?->tradeUnits->first(fn ($tradeUnit) => $tradeUnit->image_id !== null);

                return [
                    'id'                       => $proposal->id,
                    'code'                     => $product?->code,
                    'name'                     => $product?->name,
                    'image'                    => $imageOwner?->imageSources(80, 80),
                    'supplier'                 => $proposal->supplier->name,
                    'currency'                 => $proposal->supplier->currency?->code,
                    'bucket'                   => $proposal->bucket,
                    'bucket_label'             => $proposal->bucket ? __(GetSupplierStockCoverBuckets::BUCKETS[$proposal->bucket]['label'] ?? $proposal->bucket) : null,
                    'suggested_quantity_units' => (float) $proposal->suggested_quantity_units,
                    'quantity_units'           => (float) $proposal->quantity_units,
                    'units_per_carton'         => $product?->units_per_carton ? (int) $product->units_per_carton : null,
                    'unit_cost'                => (float) $proposal->unit_cost,
                    'supplier_note'            => $proposal->supplier_note,
                    'proposed_by'              => $proposal->supplierPortalContact?->name,
                    'proposed_at'              => $proposal->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }
}
