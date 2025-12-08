<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:33:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\PollOption\StorePollOption;
use App\Actions\CRM\PollOption\UpdatePollOption;
use App\Models\CRM\PollOption;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPollOptions extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:poll_options {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?PollOption
    {
        if ($pollOptionData = $organisationSource->fetchPollOption($organisationSourceId)) {
            if ($pollOption = PollOption::withTrashed()->where('source_id', $pollOptionData['poll_option']['source_id'])
                ->first()) {
                try {
                    $pollOption = UpdatePollOption::make()->action(
                        pollOption: $pollOption,
                        modelData: $pollOptionData['poll_option'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $pollOption->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $pollOptionData['poll_option'], 'PollOption', 'update');

                    return null;
                }
            } else {
                try {

                    $pollOption = StorePollOption::make()->action(
                        poll: $pollOptionData['poll'],
                        modelData: $pollOptionData['poll_option'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    PollOption::enableAuditing();
                    $this->saveMigrationHistory(
                        $pollOption,
                        Arr::except($pollOptionData['poll_option'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $pollOption->source_id);
                    DB::connection('aurora')->table('Customer Poll Query Option Dimension')
                        ->where('Customer Poll Query Option Key', $sourceData[1])
                        ->update(['aiku_id' => $pollOption->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $pollOptionData['poll_option'], 'PollOption', 'store');

                    return null;
                }
            }

            return $pollOption;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Customer Poll Query Option Dimension')
            ->select('Customer Poll Query Option Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Customer Poll Query Option Dimension')->count();
    }
}
