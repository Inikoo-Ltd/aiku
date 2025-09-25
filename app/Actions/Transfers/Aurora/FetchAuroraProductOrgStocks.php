<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Sept 2025 18:23:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Models\Catalogue\Product;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraProductOrgStocks extends FetchAuroraAction
{
    use WithAuroraAttachments;
    use HasStockLocationsFetch;
    use WithFetchStock;


    public string $commandSignature = 'fetch:product_org_stocks {organisations?*} {--S|shop= : Shop slug}  {--s|source_id=}  {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Product
    {
        $product = Product::where('source_id', $organisationSource->getOrganisation()->id.':'.$organisationSourceId)->first();
        if (!$product) {
            return $product;
        }

        $orgStocks = $organisationSource->fetchProductHasOrgStock($organisationSourceId)['org_stocks'];

        return UpdateProduct::make()->action(
            $product,
            [
                'well_formatted_org_stocks' => $orgStocks
            ]
        );
    }


    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Product Dimension')
            ->select('Product ID as source_id');

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        $query->orderBy('Product Valid From');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Product Dimension');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $sourceData = explode(':', $this->shop->source_id);
            $query->where('Product Store Key', $sourceData[1]);
        }

        return $query->count();
    }


}
