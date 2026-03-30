<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\LowStockInBasket;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Models\Comms\Outbox;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;

class RunBasketLowStockEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:basket-low-stock-notification';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::BASKET_LOW_STOCK]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->whereNotNull('interval');
        $queryOutbox->whereNotNull('threshold');

        // $queryOutbox->whereIn('id', [919]); //test for ukraine
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at', 'outboxes.interval', 'outboxes.threshold');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessLowStockInBasketPerOutbox::run($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
