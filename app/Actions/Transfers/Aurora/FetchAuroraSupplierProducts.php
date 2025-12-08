<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 21:36:34 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\OrgSupplierProducts\StoreOrgSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Actions\SupplyChain\SupplierProduct\SyncSupplierProductTradeUnits;
use App\Actions\SupplyChain\SupplierProduct\UpdateSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\SupplyChain\SupplierProduct;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraSupplierProducts extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:supplier_products {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?SupplierProduct
    {
        $supplierProductData = $organisationSource->fetchSupplierProduct($organisationSourceId);

        $supplierProduct = $this->fetchSupplierProduct($supplierProductData, $organisationSource);

        if ($supplierProduct) {
            $supplierProduct->refresh();
            $organisation = $organisationSource->getOrganisation();

            $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $organisation->id)->where('supplier_product_id', $supplierProduct->id)->first();
            if (! $orgSupplierProduct) {
                StoreOrgSupplierProduct::make()->action(
                    orgSupplier: $supplierProductData['orgSupplier'],
                    supplierProduct: $supplierProduct,
                    modelData: [
                        'source_id' => $supplierProductData['supplierProduct']['source_id'],
                    ]
                );
            }

            $this->updateSupplierProductSources($supplierProduct, $supplierProductData['supplierProduct']['source_id']);

        }

        return $supplierProduct;
    }

    public function fetchSupplierProduct($supplierProductData, $organisationSource)
    {
        $supplierProduct = null;
        if ($supplierProductData) {
            $isPrincipal = false;

            if ($supplierProduct = SupplierProduct::withTrashed()->where('source_id', $supplierProductData['supplierProduct']['source_id'])->first()) {
                try {
                    $supplierProduct = UpdateSupplierProduct::make()->action(
                        supplierProduct: $supplierProduct,
                        modelData: $supplierProductData['supplierProduct'],
                        skipHistoric: true,
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $supplierProduct->wasChanged());
                    $isPrincipal = true;
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $supplierProductData['supplierProduct'], 'SupplierProduct', 'update');

                    return null;
                }
            }

            if (! $supplierProduct) {
                $supplierProduct = SupplierProduct::withTrashed()->whereJsonContains('sources->supplier_parts', $supplierProductData['supplierProduct']['source_id'])->first();
            }
            if (! $supplierProduct) {
                $supplierProduct = SupplierProduct::withTrashed()->where('source_slug', $supplierProductData['supplierProduct']['source_slug'])->first();
            }

            if (! $supplierProduct) {
                $supplierProduct = SupplierProduct::whereRaw('LOWER(code)=? ', [trim(strtolower($supplierProductData['supplierProduct']['code']))])->first();

            }

            if (! $supplierProduct) {

                if (SupplierProduct::withTrashed()->where('supplier_id', $supplierProductData['supplier']->id)->where('code', $supplierProductData['supplierProduct']['code'])->exists()) {
                    data_set($supplierProductData, 'supplierProduct.code', $supplierProductData['supplierProduct']['code'].'-duplicated-'.uniqid());

                }

                try {
                    $supplierProduct = StoreSupplierProduct::make()->action(
                        supplier: $supplierProductData['supplier'],
                        modelData: $supplierProductData['supplierProduct'],
                        skipHistoric: true,
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordNew($organisationSource);

                    SupplierProduct::enableAuditing();
                    $this->saveMigrationHistory(
                        $supplierProduct,
                        Arr::except($supplierProductData['supplierProduct'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );
                    $isPrincipal = true;

                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $supplierProductData['supplierProduct'], 'SupplierProduct');

                    return null;
                }
            }

            if ($supplierProduct && $isPrincipal) {

                $historicSupplierProduct = FetchAuroraHistoricSupplierProducts::run($organisationSource, $supplierProductData['historicSupplierProductSourceID']);
                $supplierProduct->updateQuietly(['current_historic_supplier_product_id' => $historicSupplierProduct->id]);

                $tradeUnit = $supplierProductData['trade_unit'];
                SyncSupplierProductTradeUnits::run($supplierProduct, [
                    $tradeUnit->id => [
                        'quantity' => $supplierProductData['supplierProduct']['units_per_pack'],
                    ],
                ]);
            }
        }

        return $supplierProduct;
    }

    public function updateSupplierProductSources(SupplierProduct $supplierProduct, string $source): void
    {
        $sources = Arr::get($supplierProduct->sources, 'supplier_parts', []);
        $sources[] = $source;
        $sources = array_unique($sources);

        $supplierProduct->updateQuietly([
            'sources' => [
                'supplier_parts' => $sources,
            ],
        ]);
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Supplier Part Dimension as spp')
            ->leftJoin('Part Dimension', 'Part SKU', 'Supplier Part Part SKU')
            ->leftJoin('Supplier Dimension as sd', 'Supplier Key', 'Supplier Part Supplier Key')
            ->select('Supplier Part Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('spp.aiku_id');
        }

        return $query->where('Supplier Part Status', ['Available', 'NoAvailable'])
            ->where('Part Status', '!=', 'Not In Use')
            ->where('spp.aiku_ignore', 'No')
            ->where('sd.aiku_ignore', 'No')
            ->orderBy('Supplier Part From');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Supplier Part Dimension as spp')
            ->leftJoin('Part Dimension', 'Part SKU', 'Supplier Part Part SKU')
            ->leftJoin('Supplier Dimension as sd', 'Supplier Key', 'Supplier Part Supplier Key')
            ->select('Supplier Part Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('spp.aiku_id');
        }

        return $query->where('Supplier Part Status', ['Available', 'NoAvailable'])
            ->where('Part Status', '!=', 'Not In Use')
            ->where('spp.aiku_ignore', 'No')
            ->where('sd.aiku_ignore', 'No')
            ->count();
    }
}
