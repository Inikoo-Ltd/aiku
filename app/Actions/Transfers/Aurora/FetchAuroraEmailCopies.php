<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 13 Nov 2024 21:15:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\EmailCopy\StoreEmailCopy;
use App\Actions\Comms\EmailCopy\UpdateEmailCopy;
use App\Models\Comms\EmailCopy;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailCopies extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:email_copies {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?EmailCopy
    {
        $emailCopyData = $organisationSource->fetchEmailCopy($organisationSourceId);
        if ($emailCopyData) {
            if (! $emailCopyData['dispatchedEmail']) {
                return null;
            }

            if ($emailCopy = EmailCopy::where('source_id', $emailCopyData['emailCopy']['source_id'])->first()) {
                // try {
                $emailCopy = UpdateEmailCopy::make()->action(
                    emailCopy: $emailCopy,
                    modelData: $emailCopyData['emailCopy'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );
                //                } catch (Exception $e) {
                //                    $this->recordError($organisationSource, $e, $emailCopyData['emailCopy'], 'EmailCopy', 'update');
                //
                //                    return null;
                //                }
            } else {
                // try {
                $emailCopy = StoreEmailCopy::make()->action(
                    dispatchedEmail: $emailCopyData['dispatchedEmail'],
                    modelData: $emailCopyData['emailCopy'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );

                $this->recordNew($organisationSource);
                $sourceData = explode(':', $emailCopy->source_id);
                DB::connection('aurora')->table('Email Tracking Email Copy')
                    ->where('Email Tracking Email Copy Key', $sourceData[1])
                    ->update(['aiku_id' => $emailCopy->id]);
                //                } catch (Exception $e) {
                //                    $this->recordError($organisationSource, $e, $emailCopyData['emailCopy'], 'EmailCopy', 'store');
                //
                //                    return null;
                //                }
            }

            return $emailCopy;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Email Tracking Email Copy')
            ->select('Email Tracking Email Copy Key as source_id');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->orderBy('Email Tracking Email Copy Key');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Email Tracking Email Copy');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
