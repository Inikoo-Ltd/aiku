<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tue, 26 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\AbandonedCheckout;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAbandonedCheckoutPerOutbox
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

        $baseQuery = DB::table('checkout_abandonments');
        $baseQuery->join('customers', 'customers.id', '=', 'checkout_abandonments.customer_id');

        $baseQuery->where('checkout_abandonments.shop_id', $outbox->shop_id);
        $baseQuery->where('checkout_abandonments.state', CheckoutAbandonmentStateEnum::ABANDONED->value);
        $baseQuery->whereNull('checkout_abandonments.email_sent_at');

        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNotNull('customers.email');

        $baseQuery->select(
            'checkout_abandonments.id',
            'checkout_abandonments.order_id',
            'checkout_abandonments.customer_id',
            'customers.email'
        );
        $baseQuery->orderBy('checkout_abandonments.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chunkSize = 50;
        $baseQuery->chunkById($chunkSize, function ($abandonments) use ($emailBulkRun) {
            $customerData = $abandonments
                ->filter(fn ($abandonment) => filter_var($abandonment->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($abandonment) => [
                    'id'                      => $abandonment->customer_id,
                    'order_id'                => $abandonment->order_id,
                    'checkout_abandonment_id' => $abandonment->id,
                ])
                ->values()
                ->all();

            ProcessAbandonedCheckoutRecipients::dispatch(
                $emailBulkRun->id,
                $customerData
            );
            $this->countRecipients += count($customerData);
        }, 'checkout_abandonments.id', 'id');

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
