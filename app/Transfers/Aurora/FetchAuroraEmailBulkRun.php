<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 11:23:23 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailBulkRun extends FetchAurora
{
    protected function parseModel(): void
    {
        if ($this->auroraModelData->{'Email Template Key'} == 0) {
            return;
        }

        if (! in_array($this->auroraModelData->{'Email Campaign Type'}, [
            'GR Reminder',
            'OOS Notification',

        ])) {
            return;
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'InProcess') {
            return;
        }

        $state = match ($this->auroraModelData->{'Email Campaign State'}) {
            'Scheduled', 'Ready' => EmailBulkRunStateEnum::SCHEDULED,
            'Sending' => EmailBulkRunStateEnum::SENDING,
            'Sent' => EmailBulkRunStateEnum::SENT,
            'Cancelled' => EmailBulkRunStateEnum::CANCELLED,
            'Stopped' => EmailBulkRunStateEnum::STOPPED,
            default => null
        };
        if (! $state) {
            return;
        }

        $emailOngoingRun = $this->parseEmailOngoingRun($this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Email Template Type Key'});

        if (! $emailOngoingRun) {
            return;
        }

        $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'});
        if (! $scheduledAt) {
            $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'});
        }

        $layout = json_decode($this->auroraModelData->{'Email Template Editing JSON'}, true);
        $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Last Edited'});

        if (! $snapshotPublishedAt) {
            $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Created'});
        }
        if (! $snapshotPublishedAt) {
            $snapshotPublishedAt = now();
        }

        // $code=$emailOngoingRun->code;
        $this->parsedData['email_ongoing_run'] = $emailOngoingRun;
        $this->parsedData['email_bulk_run'] = [
            'subject' => $this->auroraModelData->{'Email Campaign Name'},
            // 'code'       => $code,
            'state' => $state,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'}),
            'scheduled_at' => $scheduledAt,
            'start_sending_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'}),
            'sent_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'}),
            'stopped_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'}),
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        $this->parsedData['snapshot'] = [
            'builder' => SnapshotBuilderEnum::BEEFREE,
            'layout' => $layout,
            'compiled_layout' => $this->auroraModelData->{'Email Template HTML'},
            'state' => SnapshotStateEnum::HISTORIC,
            'checksum' => md5(
                json_encode(
                    $layout
                )
            ),
            'published_at' => $snapshotPublishedAt,
            'recyclable' => false,
            'first_commit' => false,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->leftJoin('Email Template Dimension', 'Email Campaign Email Template Key', 'Email Template Key')
            ->where('Email Campaign Key', $id)->first();
    }
}
