<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 10:26:55 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\SourceFetch\Aurora;

use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Services\Organisation\SourceOrganisationService;

trait FetchSuppliersTrait
{
    /**
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Supplier
    {
        $supplierData = $this->fetch($organisationSource, $organisationSourceId);
        if (!$supplierData) {
            return null;
        }

        $baseSupplier = null;

        if (Supplier::withTrashed()->where('source_slug', $supplierData['supplier']['source_slug'])->exists()) {
            if ($supplier = Supplier::withTrashed()->where('source_id', $supplierData['supplier']['source_id'])->first()) {
                $supplier = UpdateSupplier::make()->run($supplier, $supplierData['supplier']);
            }
            $baseSupplier = Supplier::withTrashed()->where('source_slug', $supplierData['supplier']['source_slug'])->first();

        } else {
            $supplier = StoreSupplier::run(
                parent: $supplierData['parent'],
                modelData: $supplierData['supplier'],
            );
        }

        if ($supplier) {

            if ($supplier->agent_id) {
                OrgSupplier::where('supplier_id', $supplier->id)
                    ->where('organisation_id', $organisationSource->getOrganisation()->id)
                    ->update(
                        [
                            'source_id' => $supplierData['supplier']['source_id']
                        ]
                    );
            } else {
                StoreOrgSupplier::make()->run(
                    $organisationSource->getOrganisation(),
                    $supplier,
                    [
                        'source_id' => $supplierData['supplier']['source_id']
                    ]
                );
            }

            if (array_key_exists('photo', $supplierData)) {
                foreach ($supplierData['photo'] as $photoData) {
                    $this->saveImage($supplier, $photoData);
                }
            }
        } elseif ($baseSupplier) {
            StoreOrgSupplier::make()->run(
                $organisationSource->getOrganisation(),
                $baseSupplier,
                [
                    'source_id' => $supplierData['supplier']['source_id']
                ]
            );



        }



        return $supplier;
    }


}
