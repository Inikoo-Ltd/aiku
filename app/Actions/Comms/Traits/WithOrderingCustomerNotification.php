<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Jul 2025 21:01:30 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Traits;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
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
        if ($shopType == ShopTypeEnum::DROPSHIPPING) {
            $baseUrl = 'https://ds.test';
            if (app()->isProduction()) {
                $baseUrl = 'https://'.$shop->website->domain;
            }

            return $baseUrl.'/app/dropshipping/channels/'.$order->customerSalesChannel->slug.'/orders/'.$order->slug;
        }

        //todo for ecom and others
        return '';
    }

    public function getInvoiceLink(?Invoice $invoice): string
    {
        if (!$invoice) {
            return '';
        }

        $shop     = $invoice->shop;
        $shopType = $shop->type;

        if ($shopType == ShopTypeEnum::FULFILMENT) {
            $baseUrl = 'https://fulfilment.test';
            if (app()->isProduction()) {
                $baseUrl = 'https://'.$shop->website->domain;
            }

            return $baseUrl.'/app/fulfilment/billing/invoices/'.$invoice->slug;
        } elseif ($shopType == ShopTypeEnum::DROPSHIPPING) {
            $baseUrl = 'https://ds.test';
            if (app()->isProduction()) {
                $baseUrl = 'https://'.$shop->website->domain;
            }

            return $baseUrl.'/app/dropshipping/invoices/'.$invoice->slug;
        }

        //todo for ecom and others
        return '';
    }

    public function getEmailBody(Customer $customer, OutboxCodeEnum $outboxCode): array
    {
        $recipient       = $customer;
        if (!$recipient->email) {
            return [null, null];
        }

        /** @var Outbox $outbox */
        $outbox = $customer->shop->outboxes()->where('code', $outboxCode)->first();
        $liveSnapshot = $outbox->emailOngoingRun?->email?->liveSnapshot;
        if (!$liveSnapshot) {
            return [null, null];
        }

        $dispatchedEmail = StoreDispatchedEmail::run($outbox->emailOngoingRun, $recipient, [
            'is_test'       => false,
            'outbox_id'     => $outbox->id,
            'email_address' => $recipient->email,
            'provider'      => DispatchedEmailProviderEnum::SES
        ]);
        $dispatchedEmail->refresh();



        return [$outbox->emailOngoingRun->email->liveSnapshot->compiled_layout,$dispatchedEmail];
    }


}
