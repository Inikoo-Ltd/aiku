<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 03 Nov 2024 21:54:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

trait WithShopSetOutboxesSourceId
{
    public function setShopSetOutboxesSourceId(Shop $shop): void
    {
        $shopSourceId = explode(':', $shop->source_id);

        foreach (DB::connection('aurora')->table('Email Campaign Type Dimension')
            ->where('Email Campaign Type Store Key', $shopSourceId[1])
            ->get() as $auroraOutboxes) {

            $outboxType = $this->mapAuroraOutboxCode($auroraOutboxes->{'Email Campaign Type Code'});
            $outbox = $shop->outboxes()->where('code', $outboxType)->first();

            if ($outbox) {

                $sources = Arr::get($outbox->sources, 'outboxes', []);
                $sources[] = $shop->organisation->id.':'.$auroraOutboxes->{'Email Campaign Type Key'};
                $sources = array_unique($sources);

                $outbox->updateQuietly([
                    'sources' => [
                        'outboxes' => $sources,
                    ],
                ]);

            }
        }

    }

    private function mapAuroraOutboxCode($auroraCode): OutboxCodeEnum
    {
        return match ($auroraCode) {
            'New Customer' => OutboxCodeEnum::NEW_CUSTOMER,
            'AbandonedCart' => OutboxCodeEnum::ABANDONED_CART,
            'Basket Low Stock' => OutboxCodeEnum::BASKET_LOW_STOCK,
            'Basket Reminder 1', 'Basket Reminder 2', 'Basket Reminder 3' => OutboxCodeEnum::BASKET_PUSH,
            'Delivery Confirmation' => OutboxCodeEnum::DELIVERY_CONFIRMATION,
            'Delivery Note Dispatched' => OutboxCodeEnum::DELIVERY_NOTE_DISPATCHED,
            'Delivery Note Undispatched' => OutboxCodeEnum::DELIVERY_NOTE_UNDISPATCHED,
            'Invite', 'Invite Full Mailshot', 'Invite Mailshot' => OutboxCodeEnum::INVITE,
            'Marketing' => OutboxCodeEnum::MARKETING,
            'New Order' => OutboxCodeEnum::NEW_ORDER,
            'Newsletter' => OutboxCodeEnum::NEWSLETTER,
            'OOS Notification' => OutboxCodeEnum::OOS_NOTIFICATION,
            'Order Confirmation' => OutboxCodeEnum::ORDER_CONFIRMATION,
            'Password Reminder' => OutboxCodeEnum::PASSWORD_REMINDER,
            'Registration' => OutboxCodeEnum::REGISTRATION,
            'Registration Approved' => OutboxCodeEnum::REGISTRATION_APPROVED,
            'Registration Rejected' => OutboxCodeEnum::REGISTRATION_REJECTED,
            'GR Reminder' => OutboxCodeEnum::REORDER_REMINDER,
            'Invoice Deleted' => OutboxCodeEnum::INVOICE_DELETED,
        };
    }
}
