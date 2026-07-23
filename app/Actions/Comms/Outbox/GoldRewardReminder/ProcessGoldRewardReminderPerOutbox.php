<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed, 22 Jul 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\GoldRewardReminder;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessGoldRewardReminderPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;
    public string $jobQueue = 'ses';
    protected int $countRecipients = 0;

    public function handle(Outbox $outbox): void
    {
        $currentDateTime = Carbon::now()->utc();
        $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();

        // Build customer query using raw SQL
        $baseQuery = DB::table('customers');
        $baseQuery->leftJoin('customer_comms', 'customers.id', '=', 'customer_comms.customer_id');
        // $baseQuery->where('customer_comms.is_subscribed_to_reorder_reminder', true);
        $baseQuery->where('customers.shop_id', $outbox->shop_id);
        $baseQuery->whereDate('customers.last_invoiced_at', '=', $compareDate->toDateString());
        $baseQuery->whereNotNull('customers.email');
        $baseQuery->where('customers.email', '!=', '');
        $baseQuery->whereNull('customers.deleted_at');
        $baseQuery->select('customers.id', 'customers.email');
        $baseQuery->orderBy('customers.shop_id');
        $baseQuery->orderBy('customers.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            // create email bulk run
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chuckSize = 50;
        $baseQuery->chunk($chuckSize, function ($customers) use ($emailBulkRun) {
            $customerIds = [];
            $numValidEmails = 0;
            foreach ($customers as $customer) {
                if (filter_var($customer->email, FILTER_VALIDATE_EMAIL)) {
                    $customerIds[] = $customer->id;
                    $numValidEmails++;
                }
            }

            ProcessGoldRewardReminderRecipients::dispatch($emailBulkRun->id, $customerIds);
            $this->countRecipients += $numValidEmails;
        });

        $emailBulkRun->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $this->countRecipients
        ]);

        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        $outbox->update([
            'last_sent_at' => $currentDateTime
        ]);
    }
}
