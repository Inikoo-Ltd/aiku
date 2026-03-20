<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 21:01:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;

trait WithOrderingCustomerNotification
{
    public function getOrderLink(Order $order): string
    {
        $shop     = $order->shop;
        $shopType = $shop->type;
        $baseUrl  = 'https://'.$shop->website->domain;

        if ($shopType == ShopTypeEnum::DROPSHIPPING) {
            if (app()->isLocal()) {
                $baseUrl = 'https://ds.test';
            }

            return $baseUrl.'/app/dropshipping/channels/'.$order->customerSalesChannel->slug.'/orders/'.$order->slug;
        } elseif ($shopType == ShopTypeEnum::B2B) {
            if (app()->isLocal()) {
                $baseUrl = 'https://ecom.test';
            }

            return $baseUrl.'/app/orders/'.$order->slug;
        }

        return '';
    }

    public function getTrackingUrl(Order $order): ?string
    {
        $deliveryNote = $order->deliveryNotes->first();

        if ($deliveryNote) {
            $shipment = $deliveryNote->shipments?->first();

            if ($shipment) {
                return $shipment->combined_label_url;
            }
        }

        return '';
    }

    public function getInvoiceLink(?Invoice $invoice): string
    {
        if (!$invoice) {
            return '';
        }

        $shop     = $invoice->shop;
        $shopType = $shop->type;
        $baseUrl  = 'https://'.$shop->website->domain;

        if ($shopType == ShopTypeEnum::FULFILMENT) {
            if (app()->isLocal()) {
                $baseUrl = 'https://fulfilment.test';
            }

            return $baseUrl.'/app/fulfilment/billing/invoices/'.$invoice->slug;
        } elseif ($shopType == ShopTypeEnum::DROPSHIPPING) {
            if (app()->isLocal()) {
                $baseUrl = 'https://ds.test';
            }

            return $baseUrl.'/app/dropshipping/invoices/'.$invoice->slug;
        } elseif ($shopType == ShopTypeEnum::B2B) {
            if (app()->isLocal()) {
                $baseUrl = 'https://ecom.test';
            }

            return $baseUrl.'/app/invoices/'.$invoice->slug;
        }


        return '';
    }

    public function getPdfInvoiceLink(?Invoice $invoice): string
    {
        if (!$invoice || $invoice->ulid == '') {
            return '';
        }

        $shop     = $invoice->shop;
        $shopType = $shop->type;
        $baseUrl = 'https://'.$shop->website->domain;

        if ($shopType == ShopTypeEnum::FULFILMENT) {

            if (app()->isLocal()) {
                $baseUrl = 'https://fulfilment.test';
            }

            return $baseUrl.'/invoice/'.$invoice->ulid;
        } elseif ($shopType == ShopTypeEnum::DROPSHIPPING) {

            if (app()->isLocal()) {
                $baseUrl = 'https://ds.test';
            }

            return $baseUrl.'/invoice/'.$invoice->ulid;
        }elseif ($shopType == ShopTypeEnum::B2B) {

            if (app()->isLocal()) {
                $baseUrl = 'https://ecom.test';
            }

            return $baseUrl.'/invoice/'.$invoice->ulid;
        }

        return '';
    }

    public function getEmailBody(Customer $customer, OutboxCodeEnum $outboxCode): array
    {
        if (!$customer->email) {
            return [null, null];
        }

        /** @var Outbox $outbox */
        $outbox       = $customer->shop->outboxes()->where('code', $outboxCode)->first();
        $liveSnapshot = $outbox->emailOngoingRun?->email?->liveSnapshot;
        if (!$liveSnapshot) {
            return [null, null];
        }

        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $customer, [
            'outbox_id'     => $outbox->id,
            'email_address' => $customer->email,
        ]);
        $dispatchedEmail->refresh();


        return [$outbox->emailOngoingRun->email->liveSnapshot->compiled_layout, $dispatchedEmail];
    }


}
