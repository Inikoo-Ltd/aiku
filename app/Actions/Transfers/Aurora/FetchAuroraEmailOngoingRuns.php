<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\EmailOngoingRun\UpdateEmailOngoingRun;
use App\Actions\Comms\Outbox\UpdateOutbox;
use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunStatusEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunTypeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Email;
use App\Models\Comms\EmailOngoingRun;
use App\Models\Helpers\Snapshot;
use App\Transfers\SourceOrganisationService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailOngoingRuns extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:email_ongoing_runs {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    /**
     * @throws \Throwable
     */
    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?EmailOngoingRun
    {
        $emailOngoingRunData = $organisationSource->fetchEmailOngoingRun($organisationSourceId);
        if (! $emailOngoingRunData) {
            return null;
        }

        $emailOngoingRun = EmailOngoingRun::where('source_id', $emailOngoingRunData['email_ongoing_run']['source_id'])->first();

        if (! $emailOngoingRun) {
            $emailOngoingRun = EmailOngoingRun::where('shop_id', $emailOngoingRunData['shop']->id)
                ->where('code', $emailOngoingRunData['email_ongoing_run']['code'])
                ->first();
        }

        if (! $emailOngoingRun) {
            return null;
        }

        if ($emailOngoingRun->fetched_at) {
            data_forget($emailOngoingRunData, 'email_ongoing_run.fetched_at');
        } else {
            data_forget($emailOngoingRunData, 'email_ongoing_run.last_fetched_at');
        }

        $emailOngoingRun = UpdateEmailOngoingRun::make()->action(
            emailOngoingRun: $emailOngoingRun,
            modelData: $emailOngoingRunData['email_ongoing_run'],
            hydratorsDelay: $this->hydratorsDelay,
            strict: false,
        );
        $this->recordChange($organisationSource, $emailOngoingRun->wasChanged());
        $sourceData = explode(':', $emailOngoingRun->source_id);
        DB::connection('aurora')->table('Email Campaign Type Dimension')
            ->where('Email Campaign Type Key', $sourceData[1])
            ->update(['aiku_id' => $emailOngoingRun->id]);

        if ($emailOngoingRun->type == EmailOngoingRunTypeEnum::PUSH) {
            $emailOngoingRun = $this->processPushEmail($emailOngoingRun, $emailOngoingRunData);
            UpdateEmailOngoingRun::make()->action(
                $emailOngoingRun,
                [
                    'status' => EmailOngoingRunStatusEnum::ACTIVE,
                ]
            );

            UpdateOutbox::make()->action(
                $emailOngoingRun->outbox,
                [
                    'state' => OutboxStateEnum::ACTIVE,
                    'model_id' => $emailOngoingRun->id,
                ]
            );

            return $emailOngoingRun;
        } else {
            return $this->processEmail($emailOngoingRun, $emailOngoingRunData);
        }
    }

    private function processEmail(EmailOngoingRun $emailOngoingRun, array $emailOngoingRunData): EmailOngoingRun
    {
        $email = $emailOngoingRun->email;

        if (! $email) {
            data_forget($emailOngoingRunData, 'snapshot.last_fetched_at');

            if ($emailOngoingRunData['snapshot']['builder'] == EmailBuilderEnum::BLADE) {
                print_r($emailOngoingRunData);
                dd('This can not happen');
            }

            $email = StoreEmail::make()->action(
                $emailOngoingRun,
                null,
                modelData: $emailOngoingRunData['snapshot'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false
            );
        } elseif ($email->builder == EmailBuilderEnum::BLADE) {
            $this->processBladeSnapshots($email, $emailOngoingRunData);
        } else {
            $this->processEditableSnapshots($email->unpublishedSnapshot, $email->liveSnapshot, $emailOngoingRun, $emailOngoingRunData);
        }

        UpdateEmailOngoingRun::make()->action(
            $emailOngoingRun,
            [
                'email_id' => $email->id,
                'status' => EmailOngoingRunStatusEnum::ACTIVE,
            ]
        );

        UpdateOutbox::make()->action(
            $emailOngoingRun->outbox,
            [
                'state' => OutboxStateEnum::ACTIVE,
                'model_id' => $emailOngoingRun->id,
            ]
        );

        return $emailOngoingRun;
    }

    private function processPushEmail(EmailOngoingRun $emailOngoingRun, array $emailOngoingRunData): EmailOngoingRun
    {
        $email = $emailOngoingRun->email()->where('identifier', $emailOngoingRunData['snapshot']['identifier'])->first();

        if (! $email) {
            data_forget($emailOngoingRunData, 'snapshot.last_fetched_at');

            StoreEmail::make()->action(
                $emailOngoingRun,
                null,
                modelData: $emailOngoingRunData['snapshot'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false
            );
        } else {
            $this->processEditableSnapshots($email->unpublishedSnapshot, $email->liveSnapshot, $emailOngoingRun, $emailOngoingRunData);
        }

        return $emailOngoingRun;
    }

    private function processBladeSnapshots(Email $email, array $emailOngoingRunData): void
    {
        if ($email->liveSnapshot->fetched_at) {
            data_forget($emailOngoingRunData, 'snapshot.fetched_at');
        } else {
            data_forget($emailOngoingRunData, 'snapshot.last_fetched_at');
        }

        UpdateSnapshot::make()->action(
            $email->liveSnapshot,
            $emailOngoingRunData['snapshot'],
            hydratorsDelay: $this->hydratorsDelay,
            strict: false
        );
    }

    private function processEditableSnapshots(Snapshot $unpublishedSnapshot, ?Snapshot $liveSnapshot, EmailOngoingRun $emailOngoingRun, array $emailOngoingRunData): void
    {
        if ($unpublishedSnapshot->fetched_at) {
            data_forget($emailOngoingRunData, 'snapshot.fetched_at');
        } else {
            data_forget($emailOngoingRunData, 'snapshot.last_fetched_at');
        }

        UpdateSnapshot::make()->action(
            $unpublishedSnapshot,
            $emailOngoingRunData['snapshot'],
            hydratorsDelay: $this->hydratorsDelay,
            strict: false
        );
        if ($emailOngoingRun->status == EmailOngoingRunStatusEnum::IN_PROCESS) {
            $liveSnapshot?->delete();
        } else {
            UpdateSnapshot::make()->action(
                $liveSnapshot,
                $emailOngoingRunData['snapshot'],
                hydratorsDelay: $this->hydratorsDelay,
                strict: false
            );
        }
    }

    public function getModelsQuery(): Builder
    {
        // enum('Newsletter','Marketing','GR Reminder','AbandonedCart','Invite EmailOngoingRun','OOS Notification','Invite Full EmailOngoingRun')
        $query = DB::connection('aurora')
            ->table('Email Campaign Type Dimension');
        //            ->whereIn(
        //                'Email Campaign Type Code',
        //                [
        //                    'Registration',
        //                    'Registration Rejected',
        //                    'Registration Approved',
        //                    'Password Reminder',
        //                    'Order Confirmation',
        //                    'Delivery Confirmation'
        //                ]
        //            );

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->select('Email Campaign Type Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Email Campaign Type Dimension');
        //            ->whereIn(
        //                'Email Campaign Type Code',
        //                [
        //                    'Registration',
        //                    'Registration Rejected',
        //                    'Registration Approved',
        //                    'Password Reminder',
        //                    'Order Confirmation',
        //                    'Delivery Confirmation'
        //                ]
        //            );
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
