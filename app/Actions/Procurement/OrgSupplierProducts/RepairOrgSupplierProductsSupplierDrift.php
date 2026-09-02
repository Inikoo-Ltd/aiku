<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Sep 2026, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplierProducts;

use App\Actions\Procurement\OrgSupplier\Hydrators\OrgSupplierHydrateOrgSupplierProducts;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgSupplierProducts;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairOrgSupplierProductsSupplierDrift
{
    use AsAction;

    public string $commandSignature = 'repair:org_supplier_products_supplier_drift {--fix : Apply the repointing, otherwise dry run}';

    /**
     * @return array{repointed: int, collisions: int, unfixable: int}
     */
    public function handle(bool $fix = false): array
    {
        $repointed  = 0;
        $collisions = 0;
        $unfixable  = 0;

        $touchedOrgSuppliers = [];
        $touchedOrganisations = [];

        foreach ($this->getDriftedQuery()->cursor() as $orgSupplierProduct) {
            $correctOrgSuppliers = OrgSupplier::where('organisation_id', $orgSupplierProduct->organisation_id)
                ->where('supplier_id', $orgSupplierProduct->supplierProduct->supplier_id)
                ->get();

            if ($correctOrgSuppliers->count() != 1) {
                $unfixable++;
                continue;
            }

            $correctOrgSupplier = $correctOrgSuppliers->first();

            $hasTwin = OrgSupplierProduct::where('org_supplier_id', $correctOrgSupplier->id)
                ->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)
                ->exists();

            if ($hasTwin) {
                $collisions++;
                continue;
            }

            if ($fix) {
                $touchedOrgSuppliers[$orgSupplierProduct->org_supplier_id]  = $orgSupplierProduct->org_supplier_id;
                $touchedOrgSuppliers[$correctOrgSupplier->id]               = $correctOrgSupplier->id;
                $touchedOrganisations[$orgSupplierProduct->organisation_id] = $orgSupplierProduct->organisation_id;

                $orgSupplierProduct->update([
                    'org_supplier_id' => $correctOrgSupplier->id,
                    'org_agent_id'    => $correctOrgSupplier->org_agent_id,
                ]);
            }

            $repointed++;
        }

        foreach (OrgSupplier::whereIn('id', $touchedOrgSuppliers)->get() as $orgSupplier) {
            OrgSupplierHydrateOrgSupplierProducts::run($orgSupplier);
        }

        foreach (Organisation::whereIn('id', $touchedOrganisations)->get() as $organisation) {
            OrganisationHydrateOrgSupplierProducts::run($organisation);
        }

        return [
            'repointed'  => $repointed,
            'collisions' => $collisions,
            'unfixable'  => $unfixable,
        ];
    }

    public function getDriftedQuery()
    {
        return OrgSupplierProduct::with('supplierProduct')
            ->join('supplier_products', 'supplier_products.id', '=', 'org_supplier_products.supplier_product_id')
            ->join('org_suppliers', 'org_suppliers.id', '=', 'org_supplier_products.org_supplier_id')
            ->whereColumn('supplier_products.supplier_id', '!=', 'org_suppliers.supplier_id')
            ->select('org_supplier_products.*');
    }

    public function asCommand(Command $command): int
    {
        $fix = (bool)$command->option('fix');

        $result = $this->handle($fix);

        $command->info($fix ? 'Repointed:' : 'Would repoint:');
        $command->table(
            ['repointed', 'collisions (twin exists, needs merge)', 'unfixable (no single correct org supplier)'],
            [[$result['repointed'], $result['collisions'], $result['unfixable']]]
        );

        return 0;
    }
}
