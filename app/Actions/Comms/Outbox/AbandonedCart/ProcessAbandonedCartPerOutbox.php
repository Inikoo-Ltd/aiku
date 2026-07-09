<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Ordering\CheckoutAbandonment\CheckoutAbandonmentStateEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessAbandonedCartPerOutbox
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

        $lastOutBoxSent = $outbox->last_sent_at ?? null;

        if ($lastOutBoxSent && Carbon::parse($lastOutBoxSent)->diffInHours($currentDateTime) < $outbox->interval) {
            return;
        }

        $eligibleBefore = $currentDateTime->copy()->subHours($outbox->threshold);

        $baseQuery = DB::table('checkout_abandonments');
        $baseQuery->join('customers', 'customers.id', '=', 'checkout_abandonments.customer_id');
        $baseQuery->join('customer_comms', function ($join) {
            $join->on('customers.id', '=', 'customer_comms.customer_id')
                ->where('customer_comms.is_subscribed_to_abandoned_cart', true);
        });
        $baseQuery->where('checkout_abandonments.shop_id', $outbox->shop_id);
        $baseQuery->where('checkout_abandonments.state', CheckoutAbandonmentStateEnum::ABANDONED->value);
        $baseQuery->whereNull('checkout_abandonments.email_sent_at');
        $baseQuery->where('checkout_abandonments.checkout_visited_at', '<', $eligibleBefore);
        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->whereNotNull('customers.email');

        $baseQuery->select(
            'checkout_abandonments.id',
            'checkout_abandonments.customer_id',
            'checkout_abandonments.order_id',
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
        $baseQuery->chunk($chunkSize, function ($abandonments) use ($emailBulkRun) {
            $customerData = $abandonments
                ->filter(fn ($abandonment) => filter_var($abandonment->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($abandonment) => [
                    'id'             => $abandonment->customer_id,
                    'abandonment_id' => $abandonment->id,
                    'order_id'       => $abandonment->order_id,
                ])
                ->values()
                ->all();

            ProcessAbandonedCartRecipients::dispatch(
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
