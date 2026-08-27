<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thu, 27 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\NewCustomerPush;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessNewCustomerPushPerOutbox
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
        $maturityCutOff  = $currentDateTime->copy()->subHours(24);

        // ponytail: floor caps a stale last_sent_at; a reactivated outbox would
        // otherwise mail its entire backlog in one run.
        $windowFloor = $currentDateTime->copy()->subHours(25);
        $windowStart = Carbon::max(
            $outbox->last_sent_at ? Carbon::parse($outbox->last_sent_at) : $windowFloor,
            $windowFloor
        );

        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNotNull('customers.email');
        $baseQuery->where('customers.email', '!=', '');
        $baseQuery->whereNotNull('customers.registered_at');
        $baseQuery->where('customers.registered_at', '<=', $maturityCutOff);
        $baseQuery->where('customers.registered_at', '>', $windowStart);
        $baseQuery->whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('orders')
                ->whereColumn('orders.customer_id', 'customers.id');
        });

        $baseQuery->select('customers.id', 'customers.email');
        $baseQuery->orderBy('customers.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chunkSize = 50;
        $baseQuery->chunkById($chunkSize, function ($customers) use ($emailBulkRun) {
            $customerIds = $customers
                ->filter(fn ($customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->pluck('id')
                ->values()
                ->all();

            if (count($customerIds) === 0) {
                return;
            }

            ProcessNewCustomerPushRecipients::dispatch($emailBulkRun->id, $customerIds);
            $this->countRecipients += count($customerIds);
        }, 'customers.id', 'id');

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
