<?php

namespace App\Actions\Comms\Outbox\ReviewReminder;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessReviewReminderPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;
    public string $jobQueue = 'ses';
    protected int $countRecipients = 0;

    public function handle(Outbox $outbox): void
    {
        $currentDateTime = Carbon::now()->utc();
        $differenceInDays = $outbox->days_after ?? 10;// Default to 10 days if not set
        $baseQuery = GetReviewReminderCustomersQuery::run($outbox->shop_id, $differenceInDays, $outbox->last_sent_at ? (string) $outbox->last_sent_at : null);
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
                       'id'          => $customer->id,
                       'order_ids' => $customer->order_ids,
                   ])
                   ->values()
                   ->all();

            ProcessReviewReminderRecipients::dispatch($emailBulkRun->id, $customerData);
            $this->countRecipients += count($customerData);
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
