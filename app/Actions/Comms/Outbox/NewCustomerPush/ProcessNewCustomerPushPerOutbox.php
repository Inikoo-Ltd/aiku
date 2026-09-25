<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 21 Sep 2026 10:00:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\NewCustomerPush;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\Catalogue\Product;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessNewCustomerPushPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;

    public const int REGISTRATION_WINDOW_HOURS = 24;

    protected int $countRecipients = 0;
    public string $jobQueue = 'ses';

    public function handle(Outbox $outbox): void
    {
        $shop = $outbox->shop;
        if (!$shop->is_aiku) {
            return;
        }

        $currentDateTime = Carbon::now()->utc();

        $lastOutBoxSent = $outbox->last_sent_at ?? null;

        $productClass = class_basename(Product::class);

        $registeredSince = $currentDateTime->copy()->subHours(self::REGISTRATION_WINDOW_HOURS);

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNotNull('customers.email');
        $baseQuery->where('customers.registered_at', '>=', $registeredSince);

        if ($lastOutBoxSent) {
            $baseQuery->where('customers.registered_at', '>', $lastOutBoxSent);
        }

        $baseQuery->whereExists(function ($query) use ($productClass) {
            $query->select(DB::raw(1))
                ->from('orders')
                ->join('transactions', function ($join) use ($productClass) {
                    $join->on('transactions.order_id', '=', 'orders.id');
                    $join->where('transactions.model_type', $productClass);
                    $join->whereNull('transactions.deleted_at');
                })
                ->whereColumn('orders.customer_id', 'customers.id')
                ->where('orders.state', OrderStateEnum::CREATING->value)
                ->where('orders.status', OrderStatusEnum::CREATING->value)
                ->whereNull('orders.submitted_at')
                ->whereNull('orders.deleted_at');
        });

        $baseQuery->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('orders as placed_orders')
                ->whereColumn('placed_orders.customer_id', 'customers.id')
                ->where('placed_orders.state', '!=', OrderStateEnum::CREATING->value)
                ->whereNull('placed_orders.deleted_at');
        });

        $baseQuery->select('customers.id', 'customers.email');
        $baseQuery->orderBy('customers.id');

        $totalItems = (clone $baseQuery)->count();

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
                    'id' => $customer->id,
                ])
                ->values()
                ->all();

            ProcessNewCustomerPushRecipients::dispatch(
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
