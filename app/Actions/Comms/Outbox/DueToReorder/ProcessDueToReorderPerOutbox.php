<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Outbox\DueToReorder;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Mailshot\Filters\FilterDueToReorder;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Emails customers when they are due to reorder (see FilterDueToReorder), at most once between two
 * of their invoices. The Gold reward reminders also chase customers after their last order, so a
 * customer who got one of them in the last QUIET_DAYS days, or is about to, is left for later:
 * they get this email once the Gold reward reminders are over, if they still have not reordered.
 */
class ProcessDueToReorderPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;

    public const int QUIET_DAYS = 7;
    public const int CHUNK_SIZE = 50;

    public string $jobQueue = 'ses';

    public function handle(Outbox $outbox): void
    {
        $recipients = $this->recipientsQuery($outbox);

        if (!(clone $recipients)->exists()) {
            return;
        }

        $sentAt       = now()->utc();
        $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $sentAt->toDateTimeString());

        $countRecipients = 0;
        $recipients->chunkById(self::CHUNK_SIZE, function (Collection $customers) use ($emailBulkRun, &$countRecipients) {
            $customerIds = $customers
                ->filter(fn (object $customer) => filter_var($customer->email, FILTER_VALIDATE_EMAIL))
                ->pluck('id')
                ->all();

            if ($customerIds) {
                ProcessDueToReorderRecipients::dispatch($emailBulkRun->id, $customerIds);
                $countRecipients += count($customerIds);
            }
        }, 'customers.id', 'id');

        $emailBulkRun->update([
            'recipients_prepared_at' => now(),
            'recipients_count'       => $countRecipients
        ]);

        UpdateEmailBulkRunRecipientStoredAt::run($emailBulkRun);

        $outbox->update([
            'last_sent_at' => $sentAt
        ]);
    }

    public function recipientsQuery(Outbox $outbox): Builder
    {
        $query = DB::table('customers')
            ->join('customer_comms', 'customer_comms.customer_id', '=', 'customers.id')
            ->where('customers.shop_id', $outbox->shop_id)
            ->whereNull('customers.deleted_at')
            ->whereNotNull('customers.email')
            ->where('customers.email', '!=', '')
            ->where('customer_comms.is_subscribed_to_reorder_reminder', true)
            ->select('customers.id', 'customers.email');

        (new FilterDueToReorder())->whereDue($query, $outbox->days_after ?? FilterDueToReorder::DAYS_AHEAD);

        $query->whereNotExists(function (Builder $query) use ($outbox) {
            $this->dispatchedEmailsToCustomer($query, [$outbox->id])
                ->whereRaw("dispatched_emails.created_at >= coalesce(customers.last_invoiced_at, '-infinity')");
        });

        $goldRewardReminders = Outbox::where('shop_id', $outbox->shop_id)
            ->whereIn('code', [
                OutboxCodeEnum::GOLD_REWARD_REMINDER_1,
                OutboxCodeEnum::GOLD_REWARD_REMINDER_2,
                OutboxCodeEnum::GOLD_REWARD_REMINDER_3,
            ])
            ->get(['id', 'state', 'days_after']);

        if ($goldRewardReminders->isEmpty()) {
            return $query;
        }

        $query->whereNotExists(function (Builder $query) use ($goldRewardReminders) {
            $this->dispatchedEmailsToCustomer($query, $goldRewardReminders->pluck('id')->all())
                ->where('dispatched_emails.created_at', '>=', now()->subDays(self::QUIET_DAYS));
        });

        $upcomingGoldRewardReminderDays = $goldRewardReminders
            ->where('state', OutboxStateEnum::ACTIVE)
            ->whereNotNull('days_after')
            ->pluck('days_after')
            ->unique();

        foreach ($upcomingGoldRewardReminderDays as $daysAfter) {
            $query->where(function (Builder $query) use ($daysAfter) {
                $query->where('customer_comms.is_subscribed_to_gold_reward_reminder', false)
                    ->orWhereNull('customers.last_invoiced_at')
                    ->orWhereRaw(
                        'customers.last_invoiced_at::date + ?::int not between current_date and current_date + ?::int',
                        [$daysAfter, self::QUIET_DAYS]
                    );
            });
        }

        return $query;
    }

    private function dispatchedEmailsToCustomer(Builder $query, array $outboxIds): Builder
    {
        return $query->select(DB::raw(1))
            ->from('customer_has_dispatched_emails')
            ->join('dispatched_emails', 'dispatched_emails.id', '=', 'customer_has_dispatched_emails.dispatched_email_id')
            ->whereColumn('customer_has_dispatched_emails.customer_id', 'customers.id')
            ->whereIn('dispatched_emails.outbox_id', $outboxIds);
    }
}
