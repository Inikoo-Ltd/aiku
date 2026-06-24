<?php

namespace App\Actions\Comms\Outbox\ReviewReminder;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
        $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();

        // update this query to use the customers table instead of the customer model to avoid loading all the customer models into memory
        $baseQuery = DB::table('customers');
        $baseQuery->where('customers.shop_id', $outbox->shop_id);

        $baseQuery->rightJoin('orders', function ($join) use ($compareDate) {
            $join->on('customers.id', '=', 'orders.customer_id')
                ->where('orders.state', OrderStateEnum::DISPATCHED->value)
                ->whereNull('orders.deleted_at')
                ->whereDate('orders.dispatched_at', '=', $compareDate->toDateString());
        });

        $baseQuery->whereNotExists(function ($query) {
            $query->select('id')
                ->from('product_reviews')
                ->whereColumn('product_reviews.order_id', 'orders.id');
        });

        $baseQuery->whereNotExists(function ($query) {
            $query->select('id')
                ->from('product_category_reviews')
                ->whereColumn('product_category_reviews.order_id', 'orders.id');
        });

        $baseQuery->whereNotExists(function ($query) {
            $query->select('id')
                ->from('shop_reviews')
                ->whereColumn('shop_reviews.order_id', 'orders.id');
        });

        $baseQuery->select(
            'customers.id',
            'customers.email',
            DB::raw('STRING_AGG(orders.id::TEXT, \',\' ORDER BY orders.id) AS order_ids')
        );
        $baseQuery->groupBy('customers.id');

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
