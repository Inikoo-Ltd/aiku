<?php

namespace App\Actions\Comms\Outbox\ProspectConversion;

use App\Actions\Comms\EmailBulkRun\UpdateEmailBulkRunRecipientStoredAt;
use App\Actions\Comms\Outbox\WithGenerateEmailBulkRuns;
use App\Models\Comms\Outbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessProspectConvertionPerOutbox
{
    use WithGenerateEmailBulkRuns;
    use AsAction;
    public string $jobQueue = 'ses';
    protected int $countRecipients = 0;

    public function handle(Outbox $outbox): void
    {
        $currentDateTime = Carbon::now()->utc();
        $compareDate = $currentDateTime->copy()->subDays($outbox->days_after)->endOfDay();



        $baseQuery = DB::table('prospects');
        $baseQuery->where('prospects.shop_id', $outbox->shop_id);
        $baseQuery->where('prospects.dont_contact_me', false);
        $baseQuery->where('prospects.can_contact_by_email', true);
        $baseQuery->whereNull('prospects.customer_id');
        $baseQuery->whereNotNull('prospects.email');
        $baseQuery->whereNull('prospects.deleted_at');
        $baseQuery->whereDate('prospects.created_at', '=', $compareDate->toDateString());

        $baseQuery->select(
            'prospects.id',
            'prospects.email',
            'prospects.name',
        );

        $baseQuery->orderBy('prospects.id');

        $totalItems = (clone $baseQuery)->count();

        if ($totalItems > 0) {
            $emailBulkRun = $this->upsertEmailBulkRuns($outbox, $currentDateTime->toDateTimeString());
        } else {
            return;
        }

        $chuckSize = 50;
        $baseQuery->chunk($chuckSize, function ($prospects) use ($emailBulkRun) {
            $prospectData = $prospects
                ->filter(fn ($prospect) => filter_var($prospect->email, FILTER_VALIDATE_EMAIL))
                ->map(fn ($prospect) => [
                    'id' => $prospect->id,
                ])
                ->values()
                ->all();

            ProcessProspectConvertionRecipients::dispatch($emailBulkRun->id, $prospectData);
            $this->countRecipients += count($prospectData);
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
