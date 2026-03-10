<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox;

use App\Actions\Comms\DispatchedEmail\StoreDispatchedEmail;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRun;
use App\Actions\Comms\EmailBulkRun\StoreEmailBulkRunRecipient;
use App\Actions\Comms\EmailDeliveryChannel\SendEmailDeliveryChannel;
use App\Actions\Comms\EmailDeliveryChannel\StoreEmailDeliveryChannel;
use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRun;
use App\Actions\Comms\EmailDeliveryChannel\UpdateEmailDeliveryChannel;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\EmailBulkRun\EmailBulkRunStateEnum;
use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Comms\Outbox;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use Illuminate\Support\Carbon;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Comms\EmailBulkRun;
use Illuminate\Support\Facades\DB;

// Note: Update email template later
class RunOutOfStockInOrderEmailBulkRuns
{
    use AsAction;
    use WithGenerateEmailBulkRuns;
    use WithActionUpdate;

    public string $commandSignature = 'run:oos-in-order-notification';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            $shop = $outbox->shop;
            if (!$shop->is_aiku) {
                continue;
            }

            $currentDateTime = Carbon::now()->utc();
            $intervalInHours = Carbon::now()->utc()->subHours(24); // Check products updated in last 24 hours

            $lastOutBoxSent = $outbox->last_sent_at;

            $productClass = class_basename(Product::class);

            //  Check another condition
            $baseQuery = QueryBuilder::for(Customer::class);
            $baseQuery->where('customers.shop_id', $outbox->shop_id);

            // check customer comms
            $baseQuery->join('customer_comms', function ($join) {
                $join->on('customers.id', '=', 'customer_comms.customer_id')
                    ->where('customer_comms.is_subscribed_to_basket_low_stock', true);
            });

            // check Order
            $baseQuery->join('orders', function ($join) {
                $join->on('customers.id', '=', 'orders.customer_id');
                $join->where('orders.state', OrderStateEnum::CREATING);
                $join->where('orders.status', OrderStatusEnum::CREATING);
                $join->whereNull('orders.deleted_at');
            });

            // check Order Item
            $baseQuery->join('transactions', function ($join) {
                $join->on('orders.id', '=', 'transactions.order_id');
            });

            // check product
            $baseQuery->join('products', function ($join) use ($intervalInHours, $lastOutBoxSent, $productClass) {
                $join->on('transactions.model_id', '=', 'products.id');
                $join->where('transactions.model_type', $productClass);
                $join->where('products.is_for_sale', true);
                $join->where('products.available_quantity_updated_at', '>', $intervalInHours);
                $join->whereIn('products.state', [
                    ProductStateEnum::ACTIVE,
                    ProductStateEnum::DISCONTINUING,
                ]);
                $join->where('products.available_quantity', '<=', 0); // Out of stock

                if ($lastOutBoxSent) {
                    $join->where('products.available_quantity_updated_at', '>', $lastOutBoxSent);
                }

                $join->whereNull('products.deleted_at');
            });

            $baseQuery->select(
                'customers.id',
                'customers.email',
                DB::raw('STRING_AGG(products.id::TEXT, \',\' ORDER BY products.id) AS product_ids')
            );
            $baseQuery->groupBy('customers.id');

            $baseQuery->orderBy('customers.id');

            $totalItems = $baseQuery->clone()->count();

            // Log the query for debugging
            // \Log::info($baseQuery->toRawSql());

            if ($totalItems > 0) {
                $emailBulkRun = $this->upsertEmailBulkRunForOutOfStockInOrder($outbox, $currentDateTime->toDateTimeString());
            } else {
                return;
            }


            $baseQuery->chunk(250, function ($customers) use ($emailBulkRun, $outbox) {

                $emailDeliveryChannel = StoreEmailDeliveryChannel::run($emailBulkRun);

                foreach ($customers as $customer) {

                    if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {

                        $dispatchedEmail = StoreDispatchedEmail::run(
                            $emailBulkRun,
                            $customer,
                            [
                                'is_test'       => false,
                                'outbox_id'     => $outbox->id,
                                'email_address' => $customer->email,
                                'provider'      => DispatchedEmailProviderEnum::SES,
                                'data->additional_data' => [
                                    'products' => $this->generateProductLinks($customer->product_ids)
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
                            ]
                        );
                    }
                }

                // After processing the chunk, update and dispatch the delivery channel
                UpdateEmailDeliveryChannel::run(
                    $emailDeliveryChannel,
                    [
                        'number_emails' => $emailBulkRun->recipients()->where('channel', $emailDeliveryChannel->id)->count()
                    ]
                );
                SendEmailDeliveryChannel::dispatch($emailDeliveryChannel);
            });

            UpdateEmailBulkRun::run(
                $emailBulkRun,
                [
                    'recipients_stored_at' => now()
                ]
            );

            // update last outbox sent
            $this->update($outbox, ['last_sent_at' => $currentDateTime]);
        }
    }

    protected function upsertEmailBulkRunForOutOfStockInOrder(
        Outbox $outbox,
        ?string $date = null,
    ): EmailBulkRun {
        $date = $date ?? now()->toDateString();

        // find email bulk email for today
        $emailBulkRun = $outbox->emailBulkRuns()
            ->whereDate('created_at', $date)
            ->first();

        if ($emailBulkRun) {
            // Count existing runs for today
            $existingRunsCount = $outbox->emailBulkRuns()
                ->whereDate('created_at', $date)
                ->count();

            // Create new run with suffix
            $subject = now()->format('Y.m.d') . '_oos_' . ($existingRunsCount);

            return StoreEmailBulkRun::run($outbox->emailOngoingRun, [
                'scheduled_at' => now(),
                'subject'      => $subject,
                'state'        => EmailBulkRunStateEnum::SENDING,
            ]);
        }

        return StoreEmailBulkRun::run($outbox->emailOngoingRun, [
            'scheduled_at' => now(),
            'subject'      => now()->format('Y.m.d') . '_oos',
            'state'        => EmailBulkRunStateEnum::SENDING,
        ]);
    }

    public function asCommand(): void
    {
        $this->run();
    }

    public function generateProductLinks(string $productIds): string
    {
        try {
            $productIds = explode(',', $productIds);
        } catch (\Throwable $th) {
            \Log::error('Error parsing product IDs: ' . $th->getMessage());
            return '';
        }

        $date = Carbon::now()->format('d M y');
        $totalProducts = count($productIds);
        $displayProducts = array_slice($productIds, 0, 5);
        $remainingCount = $totalProducts - 5;

        $html = '';

        $html .= '<table width="100%">';
        // ... (rest of the HTML remains the same)
        $html .= '</table>';

        // Add "and X more" text if there are remaining products
        if ($remainingCount > 0) {
            $html .= '<p style="font-family: Helvetica, Arial, sans-serif; font-size: 14px; color: #555; margin-top: 12px;">';
            $html .= 'and ' . $remainingCount . ' more' . ($remainingCount > 1 ? 's' : '');
            $html .= '</p>';
        }

        return $html;
    }
}
