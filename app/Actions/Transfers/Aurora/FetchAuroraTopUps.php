<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 01 Nov 2024 22:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\Accounting\TopUp\UpdateTopUp;
use App\Models\Accounting\TopUp;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraTopUps extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:top_ups {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?TopUp
    {
        if ($topUpData = $organisationSource->fetchTopUp($organisationSourceId)) {
            if ($topUp = TopUp::where('source_id', $topUpData['topUp']['source_id'])
                ->first()) {
                try {
                    $topUp = UpdateTopUp::make()->action(
                        topUp: $topUp,
                        modelData: $topUpData['topUp'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $topUp->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $topUpData['topUp'], 'TopUp', 'update');

                    return null;
                }
            } else {
                try {

                    $topUp = StoreTopUp::make()->action(
                        payment: $topUpData['payment'],
                        modelData: $topUpData['topUp'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    TopUp::enableAuditing();
                    $this->saveMigrationHistory(
                        $topUp,
                        Arr::except($topUpData['topUp'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $topUp->source_id);
                    DB::connection('aurora')->table('Top Up Dimension')
                        ->where('Top Up Key', $sourceData[1])
                        ->update(['aiku_id' => $topUp->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $topUpData['topUp'], 'TopUp', 'store');

                    return null;
                }
            }

            return $topUp;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Top Up Dimension')
            ->select('Top Up Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Top Up Dimension')->count();
    }
}
