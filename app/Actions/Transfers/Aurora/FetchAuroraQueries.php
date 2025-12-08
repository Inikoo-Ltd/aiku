<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 14 Nov 2024 13:07:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Query\StoreQuery;
use App\Actions\Helpers\Query\UpdateQuery;
use App\Models\Helpers\Query;
use App\Transfers\Aurora\WithAuroraParsers;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraQueries extends FetchAuroraAction
{
    use WithAuroraParsers;

    public string $commandSignature = 'fetch:queries {organisations?*} {--s|source_id=} {--N|only_new : Fetch only new} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Query
    {

        $this->organisationSource = $organisationSource;

        $queryData = $organisationSource->fetchQuery($organisationSourceId);
        $query = null;
        if ($queryData) {
            if ($query = Query::where('source_id', $queryData['query']['source_id'])
                ->first()) {
                try {
                    $query = UpdateQuery::make()->action(
                        query: $query,
                        modelData: $queryData['query'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $query->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $queryData['query'], 'Query', 'update');

                    return null;
                }
            } else {
                try {
                    $query = StoreQuery::make()->action(
                        parent: $queryData['shop'],
                        modelData: $queryData['query'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );

                    $this->recordNew($organisationSource);
                    $sourceData = explode(':', $query->source_id);
                    DB::connection('aurora')->table('List Dimension')
                        ->where('List Key', $sourceData[1])
                        ->update(['aiku_id' => $query->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $queryData['query'], 'Query', 'store');

                    return null;
                }
            }
        }

        if ($query && $query->is_static) {
            $sourceData = explode(':', $query->source_id);
            foreach (
                DB::connection('aurora')
                    ->table('List Customer Bridge')
                    ->where('List Key', $sourceData[1])
                    ->get() as $eventData
            ) {

                $customer = $this->parseCustomer($this->organisationSource->getOrganisation()->id.':'.$eventData->{'Customer Key'});
                if ($customer) {
                    $query->customers()->attach($customer->id);
                }

            }
        }

        return $query;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('List Dimension')
            ->select('List Key as source_id');
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('List Creation Date');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('List Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
