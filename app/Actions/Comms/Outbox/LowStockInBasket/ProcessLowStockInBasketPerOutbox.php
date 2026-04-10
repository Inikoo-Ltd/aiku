<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\LowStockInBasket;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use Illuminate\Support\Carbon;
use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessLowStockInBasketPerOutbox
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

        $currentDateTime = Carbon::now()->utc();
        $intervalInHours = Carbon::now()->utc()->subHours($outbox->interval);

        $lastOutBoxSent = $outbox->last_sent_at ??  null;

        // Check if enough time has passed since last outbox was sent
        if ($lastOutBoxSent && Carbon::parse($lastOutBoxSent)->diffInHours($currentDateTime) < $outbox->interval) {
            return;
        }

        $outboxThreshold = $outbox->threshold;

        $productClass = class_basename(Product::class);

        //  Check another condition
        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');

        // check customer comms
        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_basket_low_stock', true);
        });

        // check Order
        $baseQuery->join('orders', function ($join) {
            $join->on('customers.id', '=', 'orders.customer_id');
            $join->where('orders.state', OrderStateEnum::CREATING->value);
            $join->where('orders.status', OrderStatusEnum::CREATING->value);
            $join->whereNull('orders.deleted_at');
        });

        // check Order Item
        $baseQuery->join('transactions', function ($join) use ($lastOutBoxSent) {
            $join->on('orders.id', '=', 'transactions.order_id');
            if ($lastOutBoxSent) {
                $join->where('transactions.created_at', '>', $lastOutBoxSent);
            }
            $join->whereNull('transactions.deleted_at');
        });

        // check product
        $baseQuery->join('products', function ($join) use ($intervalInHours, $lastOutBoxSent, $productClass, $outboxThreshold) {
            $join->on('transactions.model_id', '=', 'products.id');
            $join->where('transactions.model_type', $productClass);
            $join->where('products.is_for_sale', true);
            $join->where('products.available_quantity_updated_at', '>', $intervalInHours);
            $join->whereIn('products.state', [
                ProductStateEnum::ACTIVE->value,
                ProductStateEnum::DISCONTINUING->value,
            ]);
            $join->where('products.available_quantity', '<=', $outboxThreshold);

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

        $totalItems = (clone $baseQuery)->count();

        // Log the query for debugging
        // \Log::info($baseQuery->toRawSql());

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chuckSize = 50;
        $baseQuery->chunk($chuckSize, function ($customers) use ($emailBulkRun) {
            $customerData = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($customer) => [
                    'id'          => $customer->id,
                    'product_ids' => $customer->product_ids,
                ])
                ->values()
                ->all();

            ProcessBasketLowStockRecipients::dispatch(
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
