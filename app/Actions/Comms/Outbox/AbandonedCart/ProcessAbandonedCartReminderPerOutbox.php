<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAbandonedCartReminderPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;

    protected int $countRecipients = 0;
    public string $jobQueue = 'ses';

    public function handle(Outbox $outbox): void
    {
        $shop = $outbox->shop;
        if (!$shop->is_aiku) {
            return;
        }

        if (!$outbox->interval) {
            return;
        }

        $currentDateTime = Carbon::now()->utc();

        $lastOutBoxSent = $outbox->last_sent_at ??  $currentDateTime->copy()->subHours($outbox->interval + 1);

        // Check if enough time has passed since last outbox was sent
        if ($lastOutBoxSent && Carbon::parse($lastOutBoxSent)->diffInHours($currentDateTime) < $outbox->interval) {
            return;
        }

        $productClass = class_basename(Product::class);

        $compareDate = $currentDateTime->copy()->subHours($outbox->interval);

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNotNull('customers.email');

        // check customer comms
        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_abandoned_cart', true);
        });

        // check Order still in basket
        $baseQuery->whereNotNull('customers.current_order_in_basket_id');
        $baseQuery->join('orders', function ($join) {
            $join->on('customers.current_order_in_basket_id', '=', 'orders.id');
            $join->where('orders.state', OrderStateEnum::CREATING->value);
            $join->where('orders.status', OrderStatusEnum::CREATING->value);
            $join->whereNull('orders.submitted_at');
            $join->whereNull('orders.deleted_at');
        });

        // first product added to the basket must be older than the interval
        $firstItemQuery = DB::table('transactions')
            ->select('order_id', DB::raw('MIN(created_at) AS first_item_at'))
            ->where('model_type', $productClass)
            ->whereNull('deleted_at')
            ->groupBy('order_id');

        $baseQuery->joinSub($firstItemQuery, 'first_items', function ($join) use ($compareDate, $lastOutBoxSent) {
            $join->on('first_items.order_id', '=', 'orders.id');
            $join->where('first_items.first_item_at', '<=', $compareDate);
            $join->where('first_items.first_item_at', '>', $lastOutBoxSent);
        });

        $baseQuery->select(
            'orders.id',
            'orders.customer_id',
            'customers.email'
        );
        $baseQuery->orderBy('orders.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chunkSize = 50;
        $baseQuery->chunk($chunkSize, function ($orders) use ($emailBulkRun) {
            $customerData = $orders
                ->filter(fn ($order) => filter_var($order->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($order) => [
                    'id'       => $order->customer_id,
                    'order_id' => $order->id,
                ])
                ->values()
                ->all();

            ProcessAbandonedCartReminderRecipients::dispatch(
                $emailBulkRun->id,
                $customerData
            );
            $this->countRecipients += count($customerData);
        });

        $emailBulkRun->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients,
        ]);

        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        $outbox->update([
            'last_sent_at' => $currentDateTime
        ]);
    }
}
