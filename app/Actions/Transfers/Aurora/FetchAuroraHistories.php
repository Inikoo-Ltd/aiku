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
use App\Transfers\Aurora\FetchAuroraHistory;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraHistories extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:histories {organisations?*} {--s|source_id=} {--m|model= : model to Fetch } {--N|only_new : Fetch only new}  {--d|db_suffix=} {--r|reset} {--D|days= : fetch last n days} {--O|order= : order asc|desc}';


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
                    hydratorsDelay: $this->hydratorsDelay,
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
                    hydratorsDelay: $this->hydratorsDelay,
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


        return $history;
    }

    protected function getHistoryModels(): array
    {
        if ($this->model) {
            return $this->model;
        }

        return array_keys(FetchAuroraHistory::PARSERS);
    }

    public function fetchAll(SourceOrganisationService $organisationSource, ?Command $command = null): void
    {
        parent::fetchAll($organisationSource, $command);
        FetchAuroraHistory::flushSkipped();
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')->table('History Dimension');
        $query = $this->commonSelectModelsToFetch($query);


        $query->select('History Key as source_id')
            ->orderBy('History Date', $this->orderDesc ? 'desc' : 'asc');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('History Dimension');
        $query = $this->commonSelectModelsToFetch($query);

        return $query->count();
    }

    public function commonSelectModelsToFetch(Builder $query): Builder
    {
        $models       = $this->getHistoryModels();
        $sweepBlanks  = !$this->model;
        $query->where(function (Builder $query) use ($models, $sweepBlanks) {
            $query->whereIn('Direct Object', $models);
            if ($sweepBlanks) {
                $query->orWhere('Direct Object', '')
                    ->orWhereNull('Direct Object');
            }
        })
            ->whereIn('Action', ['edited', 'created', 'edit', 'merged', 'deleted', 'associated', 'disassociate', '']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        if ($this->fromDays) {
            $query->where('History Date', '>=', now()->subDays($this->fromDays)->format('Y-m-d'));
        }

        return $query;
    }


}
