<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 22 Sept 2022 02:23:37 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\HistoricAsset\UpdateHistoricAsset;
use App\Models\Catalogue\HistoricAsset;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraHistoricAssets
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $source_id): ?HistoricAsset
    {
        $historicProductData = $organisationSource->fetchHistoricAsset($source_id);
        if (! $historicProductData) {
            return null;
        }

        if ($historicProduct = HistoricAsset::withTrashed()->where('source_id', $historicProductData['historic_asset']['source_id'])
            ->first()) {
            $historicProduct = UpdateHistoricAsset::run(
                historicAsset: $historicProduct,
                modelData: $historicProductData['historic_asset'],
            );
        } else {
            $historicProduct = StoreHistoricAsset::run(
                assetModel: $historicProductData['asset_model'],
                modelData: $historicProductData['historic_asset']
            );
        }
        $sourceData = explode(':', $historicProduct->source_id);

        DB::connection('aurora')->table('Product History Dimension')
            ->where('Product Key', $sourceData[1])
            ->update(['aiku_id' => $historicProduct->id]);

        return $historicProduct;
    }
}
