<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Created: Wed, 22 Jul 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Eka Yudinata
 */

namespace App\Actions\Comms\Outbox\GoldRewardReminder;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

class RunGoldRewardReminderEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:gold-reward-reminder-email-bulk-runs';
    public string $jobQueue = 'ses';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [
            OutboxCodeEnum::GOLD_REWARD_REMINDER_1,
            OutboxCodeEnum::GOLD_REWARD_REMINDER_2,
            OutboxCodeEnum::GOLD_REWARD_REMINDER_3,
        ]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('days_after');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.days_after', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessGoldRewardReminderPerOutbox::dispatch($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
