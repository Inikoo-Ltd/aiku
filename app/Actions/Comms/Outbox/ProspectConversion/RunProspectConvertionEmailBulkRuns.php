<?php

namespace App\Actions\Comms\Outbox\ProspectConversion;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Models\CRM\Prospect;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

class RunProspectConvertionEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:prospect-convertion-email-bulk-runs';
    public string $jobQueue = 'ses';

    public function handle(?int $prospectId = null): void
    {
        $prospect = null;
        if ($prospectId) {
            $prospect = Prospect::find($prospectId);
            if (!$prospect) {
                return;
            }
        }

        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [
            OutboxCodeEnum::PROSPECT_CONVERTION_1,
            OutboxCodeEnum::PROSPECT_CONVERTION_2,
            OutboxCodeEnum::PROSPECT_CONVERTION_3,
        ]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');
        if ($prospect) {
            $queryOutbox->where('shop_id', $prospect->shop_id);
            $queryOutbox->where('days_after', 0);
        } else {
            $queryOutbox->where('days_after', '>', 0);
        }
        $queryOutbox->whereNotNull('outboxes.days_after');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.days_after', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessProspectConvertionPerOutbox::dispatch($outbox, $prospectId);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
