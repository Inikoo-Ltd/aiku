<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Nov 2024 15:35:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\CRM\PollReply\StorePollReply;
use App\Actions\CRM\PollReply\UpdatePollReply;
use App\Models\CRM\PollReply;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraPollReplies extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:poll_customers {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?PollReply
    {
        if ($pollReplyData = $organisationSource->fetchPollReply($organisationSourceId)) {
            if ($pollReply = PollReply::where('source_id', $pollReplyData['poll_reply']['source_id'])
                ->first()) {
                try {
                    $pollReply = UpdatePollReply::make()->action(
                        pollReply: $pollReply,
                        modelData: $pollReplyData['poll_reply'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );
                    $this->recordChange($organisationSource, $pollReply->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $pollReplyData['poll_reply'], 'PollReply', 'update');

                    return null;
                }
            } else {
                try {
                    $pollReply = StorePollReply::make()->action(
                        poll: $pollReplyData['poll'],
                        modelData: $pollReplyData['poll_reply'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $pollReply->source_id);
                    DB::connection('aurora')->table('Customer Poll Fact')
                        ->where('Customer Poll Key', $sourceData[1])
                        ->update(['aiku_id' => $pollReply->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $pollReplyData['poll_reply'], 'PollReply', 'store');

                    return null;
                }
            }

            return $pollReply;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Customer Poll Fact')
            ->select('Customer Poll Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Customer Poll Fact')->count();
    }
}
