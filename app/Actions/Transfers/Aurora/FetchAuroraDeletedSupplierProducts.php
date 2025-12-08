<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Feb 2023 12:46:37 Malaysia Time, Bali
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraDeletedSupplierProducts extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:deleted_supplier_products {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?SupplierProduct
    {
        if ($supplierDeletedProductData = $organisationSource->fetchDeletedSupplierProduct($organisationSourceId)) {
            if (! empty($supplierDeletedProductData['supplierProduct'])) {
                if ($supplierProduct = SupplierProduct::withTrashed()->where('source_id', $supplierDeletedProductData['supplierProduct']['source_id'])->first()) {
                    try {
                        $supplierProduct = UpdateSupplierProduct::make()->action(
                            supplierProduct: $supplierProduct,
                            modelData: $supplierDeletedProductData['supplierProduct'],
                            skipHistoric: true,
                            strict: false,
                            audit: false
                        );
                        $this->recordChange($organisationSource, $supplierProduct->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $supplierDeletedProductData['supplierProduct'], 'DeletedSupplierProduct', 'update');

                        return null;
                    }
                } else {
                    try {
                        $supplierProduct = StoreSupplierProduct::make()->action(
                            supplier: $supplierDeletedProductData['supplier'],
                            modelData: $supplierDeletedProductData['supplierProduct'],
                            skipHistoric: true,
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                            audit: false
                        );
                        $this->recordNew($organisationSource);

                        SupplierProduct::enableAuditing();
                        $this->saveMigrationHistory(
                            $supplierProduct,
                            Arr::except($supplierDeletedProductData['supplierProduct'], ['fetched_at', 'last_fetched_at', 'source_id'])
                        );

                        $sourceData = explode(':', $supplierProduct->source_id);
                        DB::connection('aurora')->table('Supplier Part Deleted Dimension')
                            ->where('Supplier Part Deleted Key', $sourceData[1])
                            ->update(['aiku_id' => $supplierProduct->id]);
                    } catch (Exception|Throwable $e) {
                        // dd($e->getMessage());
                        $this->recordError($organisationSource, $e, $supplierDeletedProductData['supplierProduct'], 'DeletedSupplierProduct');

                        return null;
                    }
                    $historicSupplierProduct = FetchAuroraHistoricSupplierProducts::run($organisationSource, $supplierDeletedProductData['historicSupplierProductSourceID']);
                    if ($historicSupplierProduct) {
                        $supplierProduct->updateQuietly(['current_historic_supplier_product_id' => $historicSupplierProduct->id]);
                    }
                }

                return $supplierProduct;
            }
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Supplier Part Deleted Dimension')
            ->select('Supplier Part Deleted Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')
            ->table('Supplier Part Deleted Dimension')
            ->select('Supplier Part Deleted Key as source_id')
            ->count();
    }
}
