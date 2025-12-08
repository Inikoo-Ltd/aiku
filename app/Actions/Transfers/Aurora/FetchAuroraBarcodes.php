<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 May 2024 17:14:23 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Barcode\StoreBarcode;
use App\Actions\Helpers\Barcode\UpdateBarcode;
use App\Models\Helpers\Barcode;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraBarcodes extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:barcodes {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Barcode
    {
        if ($barcodeData = $organisationSource->fetchBarcode($organisationSourceId)) {
            if ($barcode = Barcode::where('source_id', $barcodeData['barcode']['source_id'])->first()) {
                try {
                    $barcode = UpdateBarcode::make()->action(
                        barcode: $barcode,
                        modelData: $barcodeData['barcode'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $barcode->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $barcodeData['barcode'], 'Barcode', 'update');

                    return null;
                }
            } else {
                try {
                    $barcode = StoreBarcode::make()->action(
                        group: $organisationSource->getOrganisation()->group,
                        modelData: $barcodeData['barcode'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    Barcode::enableAuditing();
                    $this->saveMigrationHistory(
                        $barcode,
                        Arr::except($barcodeData['barcode'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $barcode->source_id);
                    DB::connection('aurora')->table('Barcode Dimension')
                        ->where('Barcode Key', $sourceData[1])
                        ->update(['aiku_id' => $barcode->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $barcodeData['barcode'], 'Barcode', 'store');

                    return null;
                }
            }

            return $barcode;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Barcode Dimension')
            ->select('Barcode Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Barcode Dimension')->count();
    }
}
