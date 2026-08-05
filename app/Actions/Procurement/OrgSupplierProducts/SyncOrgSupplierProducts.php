<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 05 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Procurement\OrgAgent\Hydrators\OrgAgentHydrateOrgSupplierProducts;
use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\SupplierProduct;
use Lorisleiva\Actions\Concerns\AsAction;

class SyncOrgSupplierProducts
{
    use AsAction;

    public function handle(OrgSupplier $orgSupplier, int $hydratorsDelay = 0): int
    {
        $supplier = $orgSupplier->supplier;

        if (!$supplier) {
            return 0;
        }

        $mirroredSupplierProductIds = $orgSupplier->orgSupplierProducts()->pluck('supplier_product_id')->all();

        $numberMirrored = 0;

        $supplier->supplierProducts()
            ->whereNotIn('supplier_products.id', $mirroredSupplierProductIds)
            ->chunkById(500, function ($supplierProducts) use ($orgSupplier, &$numberMirrored) {
                foreach ($supplierProducts as $supplierProduct) {
                    StoreOrgSupplierProduct::make()->action(
                        orgSupplier: $orgSupplier,
                        supplierProduct: $supplierProduct,
                        skipHydrators: true
                    );
                    $numberMirrored++;
                }
            });

        if ($numberMirrored) {
            $this->hydrate($orgSupplier, $hydratorsDelay);
        }

        return $numberMirrored;
    }

    public function fromSupplierProduct(SupplierProduct $supplierProduct, int $hydratorsDelay = 0): void
    {
        $supplier = $supplierProduct->supplier;

        if (!$supplier) {
            return;
        }

        foreach ($supplier->orgSuppliers as $orgSupplier) {
            $this->handle($orgSupplier, $hydratorsDelay);
        }
    }

    protected function hydrate(OrgSupplier $orgSupplier, int $hydratorsDelay): void
    {
        OrganisationHydrateOrgSupplierProducts::dispatch($orgSupplier->organisation)->delay($hydratorsDelay);
        OrgSupplierHydrateOrgSupplierProducts::dispatch($orgSupplier)->delay($hydratorsDelay);

        if ($orgSupplier->org_agent_id) {
            OrgAgentHydrateOrgSupplierProducts::dispatch($orgSupplier->orgAgent)->delay($hydratorsDelay);
        }
    }
}
