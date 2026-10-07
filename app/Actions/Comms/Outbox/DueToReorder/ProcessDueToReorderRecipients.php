<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\DueToReorder;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Actions\CRM\Customer\UI\IndexCustomerReorderProducts;
use App\Enums\Comms\EmailDeliveryChannel\EmailDeliveryChannelStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\EmailBulkRun;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Customers are checked again when their email is built: one who reordered, unsubscribed or got a
 * Gold reward reminder since the recipients were picked is skipped.
 */
class ProcessDueToReorderRecipients implements ShouldQueue
{
    use AsAction;

    public const int MAX_PRODUCTS = 5;

    public string $jobQueue = 'ses';

    public function handle(int $emailBulkRunId, array $customerIds): void
    {
        $emailBulkRun = EmailBulkRun::find($emailBulkRunId);
        $outbox       = $emailBulkRun?->outbox;

        if (!$outbox) {
            return;
        }

        $stillDueCustomerIds = ProcessDueToReorderPerOutbox::make()->recipientsQuery($outbox)
            ->whereIn('customers.id', $customerIds)
            ->pluck('customers.id');

        if ($stillDueCustomerIds->isEmpty()) {
            return;
        }

        $previousLocale = app()->getLocale();
        app()->setLocale($outbox->shop->language->code);

        $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun, [
            'state' => EmailDeliveryChannelStateEnum::IN_PROCESS->value,
        ]);

        foreach (Customer::whereIn('id', $stillDueCustomerIds)->get() as $customer) {
            $dispatchedEmail = StoreDispatchedEmail::run(
                $emailBulkRun,
                $customer,
                [
                    'outbox_id'             => $outbox->id,
                    'email_address'         => $customer->email,
                    'data->additional_data' => [
                        'products'          => $this->productsContent($customer),
                        'last_invoice_date' => $customer->last_invoiced_at?->format('Y-m-d'),
                    ]
                ]
            );

            StoreEmailBulkRunRecipient::run(
                $emailBulkRun,
                [
                    'dispatched_email_id' => $dispatchedEmail->id,
                    'recipient_type'      => class_basename($customer),
                    'recipient_id'        => $customer->id,
                    'channel'             => $emailDeliveryChannel->id,
                    'recipient_name'      => $customer->name,
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

    /**
     * @return Collection<int, Product>
     */
    public function reorderProducts(Customer $customer): Collection
    {
        return Product::query()
            ->joinSub(IndexCustomerReorderProducts::reordersQuery($customer->id), 'reorders', 'reorders.product_id', '=', 'products.id')
            ->where('products.is_for_sale', true)
            ->whereNotNull('products.webpage_id')
            ->select('products.*', 'reorders.average_quantity')
            ->orderByRaw('('.IndexCustomerReorderProducts::IS_DUE_SQL.') desc')
            ->orderByDesc('reorders.times_ordered')
            ->limit(self::MAX_PRODUCTS)
            ->get();
    }

    public function productsContent(Customer $customer): string
    {
        $products = $this->reorderProducts($customer);

        if ($products->isEmpty()) {
            return '';
        }

        $html = '<table width="100%" cellpadding="8" cellspacing="0" style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; border-collapse: collapse;">
        <tr style="border-bottom:1px solid #e5e7eb;">
            <th align="left" style="color:#555;">'.__('Product').'</th>
            <th align="center" style="color:#555;">'.__('You usually order').'</th>
            <th align="center" style="color:#555;">'.__('Price').'</th>
        </tr>';

        foreach ($products as $product) {
            $productImage = Arr::get($product->imageSources(200, 200), 'png');

            $html .= '
            <tr style="border-bottom:1px solid #f1f5f9;">
                <td style="vertical-align:middle;">
                    <table cellpadding="0" cellspacing="0"><tr>
                        <td style="padding-right:12px;">'.($productImage ? '<img src="'.$productImage.'" width="60" height="60" style="display:block; border-radius:6px; object-fit:cover;" />' : '').'</td>
                        <td style="vertical-align:middle;"><a href="'.$product->webpage->getCanonicalUrl().'" style="color:#2563eb; text-decoration:underline; font-weight:600;">'.e($product->name).'</a></td>
                    </tr></table>
                </td>
                <td align="center">'.round($product->average_quantity).'</td>
                <td align="center" style="font-weight:600;">'.($product->currency?->symbol ?? '').' '.number_format($product->price ?? 0, 2).'</td>
            </tr>';
        }

        return $html.'</table>';
    }
}
