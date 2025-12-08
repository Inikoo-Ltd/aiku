<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 15 Nov 2024 18:33:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Feedback\StoreFeedback;
use App\Actions\Helpers\Feedback\UpdateFeedback;
use App\Models\Helpers\Feedback;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraFeedbacks extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:feedbacks {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Feedback
    {
        if ($feedbackData = $organisationSource->fetchFeedback($organisationSourceId)) {
            if ($feedback = Feedback::withTrashed()->where('source_id', $feedbackData['feedback']['source_id'])
                ->first()) {
                try {
                    $feedback = UpdateFeedback::make()->action(
                        feedback: $feedback,
                        modelData: $feedbackData['feedback'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $feedback->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $feedbackData['feedback'], 'Feedback', 'update');

                    return null;
                }
            } else {
                try {

                    $feedback = StoreFeedback::make()->action(
                        origin: $feedbackData['origin'],
                        modelData: $feedbackData['feedback'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Feedback::enableAuditing();
                    $this->saveMigrationHistory(
                        $feedback,
                        Arr::except($feedbackData['feedback'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $feedback->source_id);
                    DB::connection('aurora')->table('Feedback Dimension')
                        ->where('Feedback Key', $sourceData[1])
                        ->update(['aiku_id' => $feedback->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $feedbackData['feedback'], 'Feedback', 'store');

                    return null;
                }
            }

            return $feedback;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Feedback Dimension')
            ->select('Feedback Key as source_id')
            ->orderBy('Feedback Date');

    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Feedback Dimension')->count();
    }
}
