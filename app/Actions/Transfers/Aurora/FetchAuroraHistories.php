<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\History\StoreHistory;
use App\Actions\Helpers\History\UpdateHistory;
use App\Models\Helpers\History;
use App\Models\Inventory\Location;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraHistories extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:histories {organisations?*} {--s|source_id=} {--m|model= : model to Fetch } {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset}';


    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?History
    {
        $historyData = $organisationSource->fetchHistory($organisationSourceId);
        if (!$historyData) {
            return null;
        }


        if ($history = History::where('source_id', $historyData['history']['source_id'])->first()) {
            try {
                $history = UpdateHistory::make()->action(
                    history: $history,
                    modelData: $historyData['history'],
                    hydratorsDelay: 60,
                );
                $this->recordChange($organisationSource, $history->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $historyData['history'], 'History', 'update');

                return null;
            }
        } else {
            try {
                $history = StoreHistory::make()->action(
                    auditable: $historyData['auditable'],
                    modelData: $historyData['history'],
                    hydratorsDelay: 60,
                );

                $sourceData = explode(':', $history->source_id);
                DB::connection('aurora')->table('History Dimension')
                    ->where('History Key', $sourceData[1])
                    ->update(['aiku_id' => $history->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $historyData['history'], 'History', 'store');

                return null;
            }
        }


        $this->updateModelCreatedAt($history);

        return $history;
    }


    protected function updateModelCreatedAt($history): void
    {
        if ($history->event == 'created') {
            if ($history->auditable_type == 'Location') {
                $location = Location::withTrashed()->find($history->auditable_id);
                if ($location) {
                    $location->update(['created_at' => $history->created_at]);
                }
            }
        }
    }

    protected function getHistoryModels(): array
    {
        if ($this->model) {
            return $this->model;
        }

        return ['Customer', 'Location', 'Product', 'WarehouseArea'];
    }

    public function getModelsQuery(): Builder
    {
        //enum('sold_since','last_sold','first_sold','placed','wrote','deleted','edited','cancelled','charged','merged','created','associated','disassociate','register','login','logout','fail_login','password_request','password_reset','search')
        $query = DB::connection('aurora')
            ->table('History Dimension')
            ->whereIn('Direct Object', $this->getHistoryModels())
            ->whereIn('Action', ['edited', 'created'])

            //  ->where('Direct Object', 'Product')
            //  ->where('Indirect Object', 'Product Status')

            ->select('History Key as source_id')
            ->orderBy('History Date', 'desc');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('History Dimension')
            ->whereIn('Direct Object', $this->getHistoryModels())

            //    ->where('Indirect Object', 'Product Status')

            ->whereIn('Action', ['edited', 'created']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }


}
