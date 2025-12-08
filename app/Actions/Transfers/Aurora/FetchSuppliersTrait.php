<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 10:26:55 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\Procurement\OrgSupplier\StoreOrgSupplier;
use App\Actions\SupplyChain\Supplier\StoreSupplier;
use App\Actions\SupplyChain\Supplier\UpdateSupplier;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Support\Arr;
use Throwable;

trait FetchSuppliersTrait
{
    use WithAuroraAttachments;

    /**
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist
     * @throws \Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Supplier
    {
        $supplierData = $this->fetch($organisationSource, $organisationSourceId);

        if (! $supplierData) {
            return null;
        }

        $baseSupplier = null;

        if (Supplier::withTrashed()->where('source_slug', $supplierData['supplier']['source_slug'])->exists()) {
            if ($supplier = Supplier::withTrashed()->where('source_id', $supplierData['supplier']['source_id'])->first()) {
                try {
                    $supplier = UpdateSupplier::make()->action(
                        $supplier,
                        $supplierData['supplier'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $supplier->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $supplierData['supplier'], 'Supplier', 'update');

                    return null;
                }
            }
            $baseSupplier = Supplier::withTrashed()->where('source_slug', $supplierData['supplier']['source_slug'])->first();

        } else {
            try {
                $supplier = StoreSupplier::make()->action(
                    parent: $supplierData['parent'],
                    modelData: $supplierData['supplier'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );
                Supplier::enableAuditing();
                $this->saveMigrationHistory(
                    $supplier,
                    Arr::except($supplierData['supplier'], ['fetched_at', 'last_fetched_at', 'source_id', 'source_slug'])
                );

                $this->recordNew($organisationSource);

            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $supplierData['supplier'], 'Supplier', 'store');

                return null;
            }

            foreach (Arr::get($supplierData, 'photo', []) as $photoData) {
                if (isset($photoData['image_path']) and isset($photoData['filename'])) {
                    SaveModelImage::run(
                        $supplier,
                        [
                            'path' => $photoData['image_path'],
                            'originalName' => $photoData['filename'],

                        ],
                        'photo'
                    );
                }
            }
        }
        $organisation = $organisationSource->getOrganisation();

        $effectiveSupplier = $supplier ?? $baseSupplier;

        $this->updateSupplierSources($effectiveSupplier, $supplierData['supplier']['source_id']);
        $this->createOrgSupplier($effectiveSupplier, $organisation, $supplierData, $organisationSource);
        $this->processFetchAttachments($effectiveSupplier, 'Supplier', $supplierData['supplier']['source_id']);

        return $effectiveSupplier;
    }

    public function createOrgSupplier(Supplier $supplier, Organisation $organisation, $supplierData, $organisationSource): ?OrgSupplier
    {
        $orgSupplier = OrgSupplier::where('organisation_id', $organisation->id)->where('supplier_id', $supplier->id)->first();
        if ($orgSupplier) {
            return $orgSupplier;
        }

        try {
            StoreOrgSupplier::make()->action(
                $organisation,
                $supplier,
                [
                    'source_id' => $supplierData['supplier']['source_id'],
                ],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false,
            );
        } catch (Exception|Throwable $e) {
            $this->recordError($organisationSource, $e, [], 'OrgSupplier', 'store');

            return null;
        }

        return $orgSupplier;
    }

    public function updateSupplierSources(Supplier $supplier, string $source): void
    {
        $sources = Arr::get($supplier->sources, 'suppliers', []);
        $sources[] = $source;
        $sources = array_unique($sources);

        $supplier->updateQuietly([
            'sources' => [
                'suppliers' => $sources,
            ],
        ]);
    }
}
