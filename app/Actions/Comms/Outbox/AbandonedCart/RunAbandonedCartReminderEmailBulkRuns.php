<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 09 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Comms\Outbox\AbandonedCart;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

class RunAbandonedCartReminderEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:abandoned-cart-reminder-notification';
    public string $jobQueue = 'ses';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::ABANDONED_CART_REMINDER]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('interval');

        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at', 'outboxes.interval');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessAbandonedCartReminderPerOutbox::dispatch($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
