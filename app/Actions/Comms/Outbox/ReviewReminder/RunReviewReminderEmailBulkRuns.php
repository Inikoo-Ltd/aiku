<?php

namespace App\Actions\Comms\Outbox\ReviewReminder;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

class RunReviewReminderEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:review-reminder-email-bulk-runs';
    public string $jobQueue = 'ses';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->where('code', OutboxCodeEnum::REVIEW_REMINDER);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('days_after');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.days_after', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessReviewReminderPerOutbox::dispatch($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
