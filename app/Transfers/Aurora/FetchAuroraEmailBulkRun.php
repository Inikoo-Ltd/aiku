<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 11:23:23 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\EmailBulkRun\EmailBulkRunTypeEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailBulkRun extends FetchAurora
{
    protected function parseModel(): void
    {
        if (in_array($this->auroraModelData->{'Email Campaign Type'}, [])) {
            return;
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'InProcess') {
            return;
        }


        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Store Key'});

        //enum('InProcess','SetRecipients','ComposingEmail','Ready','Scheduled','Sending','Sent','Cancelled','Stopped')
        $state = match ($this->auroraModelData->{'Email Campaign State'}) {
            'Scheduled','Ready' => EmailBulkRunStateEnum::SCHEDULED,
            'Sending' => EmailBulkRunStateEnum::SENDING,
            'Sent' => EmailBulkRunStateEnum::SENT,
            'Cancelled' => EmailBulkRunStateEnum::CANCELLED,
            'Stopped' => EmailBulkRunStateEnum::STOPPED,
            default => null
        };


        if (!$state) {

            dd($this->auroraModelData);
            return;
        }

        //enum('Newsletter','Marketing','GR Reminder','AbandonedCart','Invite Mailshot','OOS Notification','Invite Full Mailshot')


        switch ($this->auroraModelData->{'Email Campaign Type'}) {
            case 'GR Reminder':
                $type = EmailBulkRunTypeEnum::REORDER_REMINDER;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::REORDER_REMINDER)->first();

                break;
            case 'AbandonedCart':
                $type = EmailBulkRunTypeEnum::ABANDONED_CART;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::ABANDONED_CART)->first();
                break;
            case 'OOS Notification':
                $type = EmailBulkRunTypeEnum::OOS_NOTIFICATION;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::OOS_NOTIFICATION)->first();
                break;
            default:
                dd($this->auroraModelData);

        }

        if (!$outbox) {
            dd($this->auroraModelData);
        }

        $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'});
        if (!$scheduledAt) {
            $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'});
        }


        $this->parsedData['outbox']   = $outbox;
        $this->parsedData['email_bulk_run'] = [
            'subject'    => $this->auroraModelData->{'Email Campaign Name'},
            'type'       => $type,
            'state'      => $state,
            'source_id'  => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'}),
            'scheduled_at'     => $scheduledAt,
            'start_sending_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'}),
            'sent_at'          => $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'}),
            'stopped_at'       => $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'}),
            'fetched_at'        => now(),
            'last_fetched_at'   => now(),
        ];
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->where('Email Campaign Key', $id)->first();
    }
}
