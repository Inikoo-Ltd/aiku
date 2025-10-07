<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Mar 2023 04:08:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\Prospect\StoreProspect;
use App\Actions\CRM\Prospect\UpdateProspect;
use App\Models\CRM\Prospect;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraProspects extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:prospects {organisations?*} {--s|source_id=} {--S|shop= : Shop slug} {--N|only_new : Fetch only new} {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Prospect
    {
        if ($prospectData = $organisationSource->fetchProspect($organisationSourceId)) {
            if ($prospect = Prospect::withTrashed()->where('source_id', $prospectData['prospect']['source_id'])
                ->first()) {
                try {
                    $prospect = UpdateProspect::make()->action(
                        $prospect,
                        $prospectData['prospect'],
                        60,
                        false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $prospect->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $prospectData['prospect'], 'Prospect', 'update');

                    return null;
                }
            } else {
                try {
                    $prospect = StoreProspect::make()->action(
                        $prospectData['shop'],
                        $prospectData['prospect'],
                        60,
                        false,
                        audit: false
                    );
                    Prospect::enableAuditing();
                    $this->saveMigrationHistory(
                        $prospect,
                        Arr::except($prospectData['prospect'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );
                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $prospect->source_id);
                    DB::connection('aurora')->table('Prospect Dimension')
                        ->where('Prospect Key', $sourceData[1])
                        ->update(['aiku_id' => $prospect->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $prospectData['prospect'], 'Prospect', 'store');

                    return null;
                }
            }


            return $prospect;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Prospect Dimension')
            ->select('Prospect Key as source_id')
            ->orderBy('source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->shop) {
            $query->where('Prospect Store Key', $this->shop->source_id);

        }


        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Prospect Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }
        if ($this->shop) {
            $query->where('Prospect Store Key', $this->shop->source_id);
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Prospect Dimension')->update(['aiku_id' => null]);
    }
}
