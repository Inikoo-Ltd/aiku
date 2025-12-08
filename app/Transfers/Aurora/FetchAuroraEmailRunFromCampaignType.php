<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 24 Nov 2024 14:20:42 Central Indonesia Time, Kuta, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\EmailBulkRun\EmailBulkRunTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailRunFromCampaignType extends FetchAurora
{
    protected function parseModel(): void
    {

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Type Store Key'});

        //        $state = match ($this->auroraModelData->{'Email Campaign Type Code'}) {
        //            'Scheduled','Ready' => EmailBulkRunStateEnum::SCHEDULED,
        //            'Sending' => EmailBulkRunStateEnum::SENDING,
        //            'Sent' => EmailBulkRunStateEnum::SENT,
        //            'Cancelled' => EmailBulkRunStateEnum::CANCELLED,
        //            'Stopped' => EmailBulkRunStateEnum::STOPPED,
        //            default => null
        //        };
        //
        //
        //        if (!$state) {
        //            return;
        //        }

        $subject = '';

        // enum('Basket Low Stock','New Customer','Delivery Note Dispatched','Delivery Note Undispatched','Invoice Deleted','New Order','AbandonedCart','Delivery Confirmation','GR Reminder','Invite','Invite Mailshot','Invite Full Mailshot','Marketing','Newsletter','OOS Notification','Order Confirmation','Password Reminder','Registration','Registration Approved','Registration Rejected')
        switch ($this->auroraModelData->{'Email Campaign Type Code'}) {
            case 'Basket Low Stock':
                $type = EmailBulkRunTypeEnum::BASKET_LOW_STOCK;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::BASKET_LOW_STOCK)->first();
                break;
            case 'Basket Reminder 1':
            case 'Basket Reminder 2':
            case 'Basket Reminder 3':
                $type = EmailBulkRunTypeEnum::BASKET_PUSH;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::BASKET_PUSH)->first();
                break;
            case 'AbandonedCart':
                $type = EmailBulkRunTypeEnum::ABANDONED_CART;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::ABANDONED_CART)->first();
                break;
            case 'GR Reminder':
                $type = EmailBulkRunTypeEnum::REORDER_REMINDER;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::REORDER_REMINDER)->first();
                break;
            case 'OOS Notification':
                $type = EmailBulkRunTypeEnum::OOS_NOTIFICATION;
                $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::OOS_NOTIFICATION)->first();
                break;
            default:
                dd($this->auroraModelData);

        }

        if (! $outbox) {
            dd($this->auroraModelData);
        }

        $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'});
        if (! $scheduledAt) {
            $scheduledAt = $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'});
        }

        $this->parsedData['outbox'] = $outbox;
        $this->parsedData['email_run'] = [
            'subject' => $subject,
            'type' => $type,
            //  'state'      => $state,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Type Key'},
            // 'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'}),
            // 'scheduled_at'     => $scheduledAt,
            // 'start_sending_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'}),
            // / 'sent_at'          => $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'}),
            // 'stopped_at'       => $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'}),
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Campaign Type Dimension')
            ->where('Email Campaign Type Key', $id)->first();
    }
}
