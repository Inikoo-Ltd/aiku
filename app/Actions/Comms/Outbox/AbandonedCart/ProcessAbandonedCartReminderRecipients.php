<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Outbox;
use App\Models\CRM\Customer;
use App\Models\Ordering\CheckoutAbandonment;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAbandonedCartReminderRecipients
{
    use AsAction;
    use WithAbandonedCartRecoveryContent;

    public string $jobQueue = 'ses';

    public function handle(?int $emailBulkRunId, array $customers): void
    {
        if (!$emailBulkRunId) {
            return;
        }

        $emailBulkRun = EmailBulkRun::find($emailBulkRunId);

        if (!$emailBulkRun) {
            return;
        }

        $outbox = Outbox::find($emailBulkRun->outbox_id);

        if (!$outbox) {
            return;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($outbox->shop->language->code);

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun, [
            'state' => EmailDeliveryChannelStateEnum::IN_PROCESS->value,
        ]);

        $checkoutUrl = $this->getCheckoutUrl($outbox);
        $sentAbandonmentIds = [];

        foreach ($customers as $customer) {
            $customerModel = Customer::find($customer['id']);
            if (!$customerModel) {
                continue;
            }

            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $customerModel,
                [
                    'outbox_id'     => $outbox->id,
                    'email_address' => $customerModel->email,
                    'data->additional_data' => [
                        'checkout_url'         => $checkoutUrl,
                        'abandoned_cart_items' => $this->generateRecoveryContent($customer['order_id'], $checkoutUrl),
                    ]
                ]
            );

            StoreEmailBulkRunRecipient::run(
                $emailBulkRun,
                [
                    'dispatched_email_id' => $dispatchedEmail->id,
                    'recipient_type'      => class_basename($customerModel),
                    'recipient_id'        => $customerModel->id,
                    'channel'             => $emailDeliveryChannel->id,
                    'recipient_name'      => $customerModel->name,
                ]
            );

            $sentAbandonmentIds[] = $customer['abandonment_id'];
        }

        app()->setLocale($previousLocale);

        if (!empty($sentAbandonmentIds)) {
            CheckoutAbandonment::whereIn('id', $sentAbandonmentIds)->update(['email_sent_at' => now()]);
        }

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count(),
                'state'         => EmailDeliveryChannelStateEnum::READY->value
            ]
        );
        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel->id)->delay(5);
    }
}
