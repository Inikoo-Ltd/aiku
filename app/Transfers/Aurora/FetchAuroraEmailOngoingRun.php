<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 08:14:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunCodeEnum;
use App\Enums\Comms\EmailOngoingRun\EmailOngoingRunStatusEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailOngoingRun extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Type Store Key'});

        $code = match ($this->auroraModelData->{'Email Campaign Type Code'}) {
            'Registration' => EmailOngoingRunCodeEnum::REGISTRATION,
            'Registration Rejected' => EmailOngoingRunCodeEnum::REGISTRATION_REJECTED,
            'Registration Approved' => EmailOngoingRunCodeEnum::REGISTRATION_APPROVED,
            'Password Reminder' => EmailOngoingRunCodeEnum::PASSWORD_REMINDER,
            'Order Confirmation' => EmailOngoingRunCodeEnum::ORDER_CONFIRMATION,
            'Delivery Confirmation' => EmailOngoingRunCodeEnum::DELIVERY_CONFIRMATION,

            'Basket Low Stock' => EmailOngoingRunCodeEnum::BASKET_LOW_STOCK,
            'Basket Reminder 1', 'Basket Reminder 2', 'Basket Reminder 3' => EmailOngoingRunCodeEnum::BASKET_PUSH,
            'AbandonedCart' => EmailOngoingRunCodeEnum::ABANDONED_CART,
            'GR Reminder' => EmailOngoingRunCodeEnum::REORDER_REMINDER,
            'OOS Notification' => EmailOngoingRunCodeEnum::OOS_NOTIFICATION,

            'New Customer' => EmailOngoingRunCodeEnum::NEW_CUSTOMER,
            'Delivery Note Dispatched' => EmailOngoingRunCodeEnum::DELIVERY_NOTE_DISPATCHED,
            'Delivery Note Undispatched' => EmailOngoingRunCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            'Invoice Deleted' => EmailOngoingRunCodeEnum::INVOICE_DELETED,
            'New Order' => EmailOngoingRunCodeEnum::NEW_ORDER,
            default => null
        };

        $createdAt = $this->parseDatetime($this->auroraModelData->{'Email Template Created'});
        if (! $createdAt) {
            $createdAt = $this->parseDatetime($this->auroraModelData->{'Email Template Last Edited'});
        }

        // enum(','New Customer','Delivery Note Dispatched','Delivery Note Undispatched','Invoice Deleted','New Order','AbandonedCart','Delivery Confirmation','GR Reminder','Invite','Invite Mailshot','Invite Full Mailshot','Marketing','Newsletter','OOS Notification','Order Confirmation','Password Reminder','Registration','Registration Approved','Registration Rejected')

        if (in_array(
            $this->auroraModelData->{'Email Campaign Type Code'},
            [
                'New Customer',
                'Delivery Note Dispatched',
                'Delivery Note Undispatched',
                'Invoice Deleted',
                'New Order',

            ]
        )) {
            $this->parsedData['shop'] = $shop;

            $this->parsedData['email_ongoing_run'] = [
                'code' => $code,
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Type Key'},
                'created_at' => $createdAt,
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];

            $this->parsedData['snapshot'] = [
                'subject' => $this->auroraModelData->{'Email Template Subject'},
                'builder' => EmailBuilderEnum::BLADE,
                'fetched_at' => now(),
                'last_fetched_at' => now(),
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},

            ];

            return;
        }

        if (! in_array(
            $this->auroraModelData->{'Email Campaign Type Code'},
            [
                'Registration',
                'Registration Rejected',
                'Registration Approved',
                'Password Reminder',
                'Order Confirmation',
                'Delivery Confirmation',
                'OOS Notification',
                'Basket Low Stock',
                'Basket Reminder 1',
                'Basket Reminder 2',
                'Basket Reminder 3',
                'AbandonedCart',
                'GR Reminder',
            ]
        )) {
            return;
        }

        if (! $this->auroraModelData->{'Email Template Key'}) {
            return;
        }

        $status = match ($this->auroraModelData->{'Email Campaign Type Status'}) {
            'Active' => EmailOngoingRunStatusEnum::ACTIVE,
            'Suspended' => EmailOngoingRunStatusEnum::SUSPENDED,
            'InProcess' => EmailOngoingRunStatusEnum::IN_PROCESS,
        };

        $this->parsedData['shop'] = $shop;

        $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Last Edited'});

        if (! $snapshotPublishedAt) {
            $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Created'});
        }
        if (! $snapshotPublishedAt) {
            $snapshotPublishedAt = now();
        }

        if ($status == EmailOngoingRunStatusEnum::IN_PROCESS) {
            $snapshotPublishedAt = null;
        }

        $this->parsedData['email_ongoing_run'] = [
            'code' => $code,
            'status' => $status,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Type Key'},
            'created_at' => $createdAt,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        $snapshotState = match ($status) {
            EmailOngoingRunStatusEnum::IN_PROCESS => SnapshotStateEnum::UNPUBLISHED,
            default => SnapshotStateEnum::LIVE,
        };

        $compiledLayout = $this->auroraModelData->{'Email Template HTML'};
        if ($compiledLayout == 0) {
            $compiledLayout = '';
        }

        $this->parsedData['snapshot'] = [
            'subject' => $this->auroraModelData->{'Email Template Subject'},
            'builder' => EmailBuilderEnum::BEEFREE,
            'layout' => json_decode($this->auroraModelData->{'Email Template Editing JSON'}, true),
            'compiled_layout' => $compiledLayout,
            'state' => $snapshotState,
            'published_at' => $snapshotPublishedAt,
            'recyclable' => false,
            'first_commit' => true,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},

            'snapshot_state' => $snapshotState,
            'snapshot_published_at' => $snapshotPublishedAt,
            'snapshot_recyclable' => false,
            'snapshot_first_commit' => true,
            'snapshot_source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},

        ];

        if ($code == EmailOngoingRunCodeEnum::BASKET_PUSH) {
            $this->parsedData['snapshot']['identifier'] = $this->auroraModelData->{'Email Campaign Type Code'};
        }

        // dd($this->auroraModelData);
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Campaign Type Dimension')
            ->leftJoin('Email Template Dimension', 'Email Template Email Campaign Type Key', 'Email Campaign Type Key')
            ->where('Email Campaign Type Key', $id)->first();
    }
}
