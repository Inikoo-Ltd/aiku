<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 14:28:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\SupplyChain\HistoricSupplierProduct\StoreHistoricSupplierProduct;
use App\Actions\SupplyChain\HistoricSupplierProduct\UpdateHistoricSupplierProduct;
use App\Models\SupplyChain\HistoricSupplierProduct;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraHistoricSupplierProducts
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $source_id): ?HistoricSupplierProduct
    {
        $historicProductData = $organisationSource->fetchHistoricSupplierProduct($source_id);
        if (! $historicProductData) {
            return null;
        }

        $historicSupplierProduct = HistoricSupplierProduct::where('source_id', $historicProductData['historic_supplier_product']['source_id'])->first();

        if ($historicSupplierProduct) {
            $historicSupplierProduct = UpdateHistoricSupplierProduct::make()->action(
                historicSupplierProduct: $historicSupplierProduct,
                modelData: $historicProductData['historic_supplier_product'],
                hydratorsDelay: 5,
                strict: false
            );
        } else {
            $historicSupplierProduct = HistoricSupplierProduct::whereJsonContains('sources->historic_supplier_parts', $historicProductData['historic_supplier_product']['source_id'])->first();
        }

        if (! $historicSupplierProduct) {
            $historicSupplierProduct = HistoricSupplierProduct::where('units_per_pack', $historicProductData['historic_supplier_product']['units_per_pack'])
                ->where('supplier_product_id', $historicProductData['supplier_product']->id)
                ->where('cbm', Arr::get($historicProductData, 'historic_supplier_product.cbm'))
                ->where('code', $historicProductData['historic_supplier_product']['code'])
                ->where('units_per_pack', $historicProductData['historic_supplier_product']['units_per_pack'])
                ->where('units_per_carton', $historicProductData['historic_supplier_product']['units_per_carton'])
                ->first();
        }

        if (! $historicSupplierProduct) {
            $historicSupplierProduct = StoreHistoricSupplierProduct::make()->action(
                supplierProduct: $historicProductData['supplier_product'],
                modelData: $historicProductData['historic_supplier_product'],
                hydratorsDelay: 5,
                strict: false
            );
        }

        if ($historicSupplierProduct) {
            $this->updateHistoricSupplierProductSources($historicSupplierProduct, $historicProductData['historic_supplier_product']['source_id']);

            $sourceData = explode(':', $historicSupplierProduct->source_id);

            DB::connection('aurora')->table('Supplier Part Historic Dimension')
                ->where('Supplier Part Historic Key', $sourceData[1])
                ->update(['aiku_supplier_historic_product_id' => $historicSupplierProduct->id]);
        }

        return $historicSupplierProduct;
    }

    public function updateHistoricSupplierProductSources(HistoricSupplierProduct $historicSupplierProduct, string $source): void
    {
        $sources = Arr::get($historicSupplierProduct->sources, 'supplier_parts', []);
        $sources[] = $source;
        $sources = array_unique($sources);

        $historicSupplierProduct->updateQuietly([
            'sources' => [
                'historic_supplier_parts' => $sources,
            ],
        ]);
    }
}
