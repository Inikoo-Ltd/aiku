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

        $baseQuery = GetBackInStockCustomersQuery::run($outbox->shop_id, GetBackInStockCustomersQuery::EMAIL);

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
