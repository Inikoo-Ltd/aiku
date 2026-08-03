<?php

namespace App\Actions\Comms\Outbox\ReviewReminder;

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
use App\Models\Ordering\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessReviewReminderRecipients implements ShouldQueue
{
    use AsAction;

    public string $jobQueue = 'ses';

    public function handle(int $emailBulkRunId, array $customers): void
    {
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

        foreach ($customers as $customer) {
            $customerModel = Customer::find($customer['id']);
            if (!$customerModel) {
                continue;
            }

            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $customerModel,
                [
                    'outbox_id'     => $emailBulkRun->outbox_id,
                    'email_address' => $customerModel->email,
                    'data->additional_data' => [
                        'review_reminder_links' => $this->generateReviewLinks($customer['order_ids'])
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
        }

        app()->setLocale($previousLocale);

        UpdateEmailDeliveryChannel::run(
            $emailDeliveryChannel,
            [
                'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count(),
                'state'         => EmailDeliveryChannelStateEnum::READY->value
            ]
        );
        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        SendEmailDeliveryChannel::dispatch($emailDeliveryChannel->id)->delay(2);
    }

    public function generateReviewLinks(string $orderIds): string
    {
        try {
            $orderIds = explode(',', $orderIds);
        } catch (\Throwable $th) {
            \Log::error('Error parsing order IDs: ' . $th->getMessage());
            return '';
        }

        $html = '<ul>';
        foreach ($orderIds as $orderId) {
            $order = Order::find($orderId);
            if (!$order) {
                continue;
            }

            $url = $order?->shop?->website?->getUrl() .'/app/orders/'. $order->slug . '/review' ?? '#';


            $html .= sprintf(
                '<li><a href="%s">%s</a></li>',
                $url,
                __('Review in your order').' #' . $order->slug
            );
        }
        $html .= '</ul>';

        return $html;
    }
}
