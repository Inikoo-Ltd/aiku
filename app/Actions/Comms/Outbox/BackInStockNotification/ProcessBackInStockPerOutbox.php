<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Thu, 19 Dec 2025 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\BackInStockNotification;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessBackInStockPerOutbox
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
        $lastOutBoxSent = $outbox->last_sent_at;

        // make customers as the main table
        $baseQuery = DB::table('customers');
        $baseQuery->join('back_in_stock_reminders', 'customers.id', '=', 'back_in_stock_reminders.customer_id');
        $baseQuery->join('products', 'back_in_stock_reminders.product_id', '=', 'products.id');
        // select options
        $baseQuery->select(
            'customers.id',
            'customers.email',
            'customers.shop_id',
            DB::raw('STRING_AGG(back_in_stock_reminders.id::TEXT, \',\' ORDER BY back_in_stock_reminders.id) AS reminder_ids'),
            DB::raw('STRING_AGG(back_in_stock_reminders.product_id::TEXT, \',\' ORDER BY back_in_stock_reminders.product_id) AS product_ids')
        );

        // where conditions
        $baseQuery->where('back_in_stock_reminders.shop_id', $outbox->shop_id);
        $baseQuery->where('products.available_quantity', '>', 0);
        $baseQuery->where('products.back_in_stock_since', '>', DB::raw('back_in_stock_reminders.created_at'));

        if ($lastOutBoxSent) {
            $baseQuery->where('products.back_in_stock_since', '>', $lastOutBoxSent);
        }

        // check another customers condition
        $baseQuery->whereNull('customers.deleted_at');
        // check another product condition
        $baseQuery->whereNull('products.deleted_at');
        // order by customer id
        $baseQuery->orderBy('customers.id');
        $baseQuery->groupBy('customers.id');

        // \Log::info($baseQuery->toRawSql());

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chunkSize = 50;

        $baseQuery->chunk($chunkSize, function ($customers) use ($emailBulkRun) {
            $customerData = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($customer) => [
                    'id'           => $customer->id,
                    'email'        => $customer->email,
                    'product_ids'  => $customer->product_ids,
                    'reminder_ids' => $customer->reminder_ids
                ])
                ->values()
                ->all();

            ProcessBackInStockRecipient::dispatch(
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
