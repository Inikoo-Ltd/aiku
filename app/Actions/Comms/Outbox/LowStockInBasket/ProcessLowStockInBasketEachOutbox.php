<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\LowStockInBasket;

use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use Illuminate\Support\Carbon;
use App\Models\Catalogue\Product;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessLowStockInBasketEachOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;
    protected int $countRecipients = 0;

    public function handle(Outbox $outbox): void
    {
        $shop = $outbox->shop;
        if (!$shop->is_aiku) {
            return;
        }

        $currentDateTime = Carbon::now()->utc();
        $intervalInHours = Carbon::now()->utc()->subHours($outbox->interval);

        $lastOutBoxSent = $outbox->last_sent_at;

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
        $baseQuery->join('transactions', function ($join) {
            $join->on('orders.id', '=', 'transactions.order_id');
        });

        // check product
        $baseQuery->join('products', function ($join) use ($intervalInHours, $lastOutBoxSent, $productClass, $outboxThreshold) {
            $join->on('transactions.model_id', '=', 'products.id');
            $join->where('transactions.model_type', $productClass);
            $join->where('products.is_for_sale', true);
            // $join->where('products.available_quantity_updated_at', '>', $intervalInHours);
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
            $instance = new self();
            $emailBulkRun = $instance->upsertEmailBulkRunForBasketLowStock($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chuckSize = 50;
        $baseQuery->chunk($chuckSize, function ($customers) use ($emailBulkRun) {
            $customerData = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($customer) => [
                    'id'          => $customer->id,
                    'email'       => $customer->email,
                    'product_ids' => $customer->product_ids,
                ])
                ->values()
                ->all();

            ProcessBasketLowStockCustomers::dispatch(
                $emailBulkRun->id,
                $customerData
            );
            $this->countRecipients += count($customerData);
        });

        $emailBulkRun->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients,
        ]);

        // UpdateEmailBulkRun::run(
        //     $emailBulkRun,
        //     [
        //         'recipients_stored_at' => now()
        //     ]
        // );

        // // update last outbox sent
        // $instance->update($outbox, ['last_sent_at' => $currentDateTime]);
    }
}
