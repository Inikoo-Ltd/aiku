<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 06 Dec 2022 18:17:28 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\HistoricAsset\UpdateHistoricAsset;
use App\Models\Catalogue\HistoricAsset;
use App\Transfers\SourceOrganisationService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FetchAuroraHistoricServices
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $source_id): ?HistoricAsset
    {
        if ($historicProductData = $organisationSource->fetchHistoricService($source_id)) {
            if ($historicProduct = HistoricAsset::withTrashed()->where('source_id', $historicProductData['historic_service']['source_id'])
                ->first()) {
                $historicProduct = UpdateHistoricAsset::run(
                    historicAsset: $historicProduct,
                    modelData: $historicProductData['historic_service'],
                );
            } else {
                $historicProduct = StoreHistoricAsset::run(
                    assetModel: $historicProductData['service'],
                    modelData: $historicProductData['historic_service']
                );
            }
            $sourceData = explode(':', $historicProduct->source_id);

            DB::connection('aurora')->table('Product History Dimension')
                ->where('Product Key', $sourceData[1])
                ->update(['aiku_id' => $historicProduct->id]);

            return $historicProduct;
        }

        return null;
    }
}
