<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Jul 2024 11:35:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Charge\UpdateCharge;
use App\Models\Billables\Charge;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraCharges extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:charges {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Charge
    {
        if ($chargeData = $organisationSource->fetchCharge($organisationSourceId)) {
            if ($charge = Charge::where('source_id', $chargeData['charge']['source_id'])->first()) {
                try {
                    $charge = UpdateCharge::make()->action(
                        charge: $charge,
                        modelData: $chargeData['charge'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $charge->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $chargeData['charge'], 'Charge', 'update');

                    return null;
                }
            } else {
                try {
                    $charge = StoreCharge::make()->action(
                        shop: $chargeData['shop'],
                        modelData: $chargeData['charge'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    Charge::enableAuditing();
                    $this->saveMigrationHistory(
                        $charge,
                        Arr::except($chargeData['charge'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $charge->source_id);
                    DB::connection('aurora')->table('Charge Dimension')
                        ->where('Charge Key', $sourceData[1])
                        ->update(['aiku_id' => $charge->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $chargeData['charge'], 'Charge', 'store');

                    return null;
                }
            }

            return $charge;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Charge Dimension')
            ->select('Charge Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Charge Dimension')->count();
    }
}
