<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 13:06:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\HumanResources\ClockingMachine\StoreClockingMachine;
use App\Actions\HumanResources\ClockingMachine\UpdateClockingMachine;
use App\Models\HumanResources\ClockingMachine;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraClockingMachines extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:clocking_machines {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?ClockingMachine
    {
        if ($clockingMachineData = $organisationSource->fetchClockingMachine($organisationSourceId)) {
            if ($clockingMachine = ClockingMachine::where('source_id', $clockingMachineData['clocking-machine']['source_id'])->first()) {
                try {
                    $clockingMachine = UpdateClockingMachine::make()->action(
                        clockingMachine: $clockingMachine,
                        modelData: $clockingMachineData['clocking-machine'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $clockingMachine->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $clockingMachineData['clocking-machine'], 'ClockingMachine', 'update');

                    return null;
                }
            } else {
                try {
                    $clockingMachine = StoreClockingMachine::make()->action(
                        workplace: $clockingMachineData['workplace'],
                        modelData: $clockingMachineData['clocking-machine'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    ClockingMachine::enableAuditing();
                    $this->saveMigrationHistory(
                        $clockingMachine,
                        Arr::except($clockingMachineData['clocking-machine'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $clockingMachine->source_id);
                    DB::connection('aurora')->table('Clocking Machine Dimension')
                        ->where('Clocking Machine Key', $sourceData[1])
                        ->update(['aiku_id' => $clockingMachine->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $clockingMachineData['clocking-machine'], 'ClockingMachine', 'store');

                    return null;
                }

            }

            return $clockingMachine;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Clocking Machine Dimension')
            ->select('Clocking Machine Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Clocking Machine Dimension')->count();
    }
}
