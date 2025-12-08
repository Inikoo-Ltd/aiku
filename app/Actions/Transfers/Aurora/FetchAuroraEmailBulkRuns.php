<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 11:36:53 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRun;
use App\Actions\Helpers\Snapshot\StoreEmailSnapshot;
use App\Models\Comms\EmailBulkRun;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraEmailBulkRuns extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:email_bulk_runs {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?EmailBulkRun
    {
        $emailRunData = $organisationSource->fetchEmailBulkRun($organisationSourceId);
        if (! $emailRunData) {
            return null;
        }

        $snapshotId = null;
        $emailOnGoingRun = $emailRunData['email_ongoing_run'];

        if (Arr::has($emailRunData, 'snapshot')) {
            if ($emailOnGoingRun->email->unpublishedSnapshot->checksum == $emailRunData['snapshot']['checksum']) {
                $snapshotId = $emailOnGoingRun->email->unpublished_snapshot_id;
            }

            if (! $snapshotId) {
                $snapshot = $emailOnGoingRun->email->snapshots()->where('checksum', $emailRunData['snapshot']['checksum'])->first();
                if ($snapshot) {
                    echo "Historic Snapshot Taken\n";
                    $snapshotId = $snapshot->id;
                }
            }

            if (! $snapshotId) {
                $snapshot = StoreEmailSnapshot::make()->action(
                    email: $emailOnGoingRun->email,
                    modelData: $emailRunData['snapshot'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );
                $snapshotId = $snapshot->id;
                echo "New Snapshot\n";
            }
        } else {
            $snapshotId = $emailOnGoingRun->email->snapshot_id;
        }

        if (! $snapshotId) {
            dd($emailRunData);
        }

        $emailRunData['email_bulk_run']['snapshot_id'] = $snapshotId;

        if ($emailRun = EmailBulkRun::where('source_id', $emailRunData['email_bulk_run']['source_id'])->first()) {
            try {
                $emailRun = UpdateEmailBulkRun::make()->action(
                    emailRun: $emailRun,
                    modelData: $emailRunData['email_bulk_run'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );
                $this->recordChange($organisationSource, $emailRun->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $emailRunData['email_bulk_run'], 'EmailBulkRun', 'update');

                return null;
            }
        } else {
            try {
                $emailRun = StoreEmailBulkRun::make()->action(
                    emailOngoingRun: $emailRunData['email_ongoing_run'],
                    modelData: $emailRunData['email_bulk_run'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $emailRun->source_id);
                DB::connection('aurora')->table('Email Campaign Dimension')
                    ->where('Email Campaign Key', $sourceData[1])
                    ->update(['alt_aiku_id' => $emailRun->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $emailRunData['email_bulk_run'], 'EmailBulkRun', 'store');

                return null;
            }
        }

        return $emailRun;
    }

    public function getModelsQuery(): Builder
    {
        // enum('Newsletter','Marketing','GR Reminder','AbandonedCart','Invite Mailshot','OOS Notification','Invite Full Mailshot')
        $query = DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->whereIn('Email Campaign Type', ['GR Reminder', 'OOS Notification']);

        if ($this->onlyNew) {
            $query->whereNull('alt_aiku_id');
        }

        return $query->select('Email Campaign Key as source_id')
            ->orderBy('Email Campaign Creation Date');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->whereIn('Email Campaign Type', ['GR Reminder', 'OOS Notification']);
        if ($this->onlyNew) {
            $query->whereNull('alt_aiku_id');
        }

        return $query->count();
    }
}
