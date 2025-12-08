<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Apr 2024 13:06:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\HumanResources\Clocking\StoreClocking;
use App\Actions\HumanResources\Clocking\UpdateClocking;
use App\Actions\HumanResources\Timesheet\StoreTimesheet;
use App\Actions\HumanResources\Timesheet\UpdateTimesheet;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\Timesheet;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraTimesheets extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:timesheets {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Timesheet
    {
        if ($timesheetData = $organisationSource->fetchTimesheet($organisationSourceId)) {
            if (! $timesheetData['employee'] or $timesheetData['employee']->trashed()) {
                return null;
            }

            if ($timesheet = Timesheet::where('source_id', $timesheetData['timesheet']['source_id'])->first()) {
                try {
                    $timesheet = UpdateTimesheet::make()->action(
                        timesheet: $timesheet,
                        modelData: $timesheetData['timesheet'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $timesheet->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $timesheetData['timesheet'], 'Timesheet', 'update');

                    return null;
                }
            } else {
                try {
                    $timesheet = StoreTimesheet::make()->action(
                        parent: $timesheetData['employee'],
                        modelData: $timesheetData['timesheet'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordNew($organisationSource);
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $timesheetData['timesheet'], 'Timesheet', 'store');

                    return null;
                }

            }

            foreach ($timesheetData['clockings'] as $clockingData) {

                if ($clocking = Clocking::withTrashed()->where('source_id', $clockingData['clockingData']['source_id'])->first()) {

                    try {
                        $clocking = UpdateClocking::make()->action(
                            clocking: $clocking,
                            modelData: $clockingData['clockingData'],
                            hydratorsDelay: $this->hydratorsDelay,
                            strict: false,
                        );
                        $this->recordChange($organisationSource, $clocking->wasChanged());
                    } catch (Exception $e) {
                        $this->recordError($organisationSource, $e, $clockingData['clockingData'], 'Clocking', 'update');

                        return null;
                    }
                } else {
                    try {
                        $clocking = StoreClocking::make()->action(
                            generator: $clockingData['generator'],
                            parent: $clockingData['parent'],
                            subject: $clockingData['subject'],
                            modelData: $clockingData['clockingData'],
                            hydratorsDelay: 120,
                            strict: false,
                        );
                        $this->recordNew($organisationSource);
                        $sourceData = explode(':', $clocking->source_id);
                        DB::connection('aurora')->table('Timesheet Record Dimension')
                            ->where('Timesheet Record Key', $sourceData[1])
                            ->update(['aiku_id' => $timesheet->id]);
                    } catch (Exception|Throwable $e) {
                        $this->recordError($organisationSource, $e, $clockingData['clockingData'], 'Clocking', 'store');

                        return null;
                    }
                }
            }

            return $timesheet;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Timesheet Dimension')
            ->select('Timesheet Key as source_id')
            ->orderBy('Timesheet Date');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Timesheet Dimension')->count();
    }
}
