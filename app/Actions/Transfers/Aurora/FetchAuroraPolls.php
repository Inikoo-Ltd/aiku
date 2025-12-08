<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:33:11 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\Poll\StorePoll;
use App\Actions\CRM\Poll\UpdatePoll;
use App\Models\CRM\Poll;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPolls extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:polls {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Poll
    {
        if ($pollData = $organisationSource->fetchPoll($organisationSourceId)) {
            if ($poll = Poll::withTrashed()->where('source_id', $pollData['poll']['source_id'])
                ->first()) {
                try {
                    $poll = UpdatePoll::make()->action(
                        poll: $poll,
                        modelData: $pollData['poll'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $poll->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $pollData['poll'], 'Poll', 'update');

                    return null;
                }
            } else {
                try {
                    $poll = StorePoll::make()->action(
                        shop: $pollData['shop'],
                        modelData: $pollData['poll'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Poll::enableAuditing();
                    $this->saveMigrationHistory(
                        $poll,
                        Arr::except($pollData['poll'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $poll->source_id);
                    DB::connection('aurora')->table('Customer Poll Query Dimension')
                        ->where('Customer Poll Query Key', $sourceData[1])
                        ->update(['aiku_id' => $poll->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $pollData['poll'], 'Poll', 'store');

                    return null;
                }
            }

            return $poll;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Customer Poll Query Dimension')
            ->select('Customer Poll Query Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Customer Poll Query Dimension')->count();
    }
}
