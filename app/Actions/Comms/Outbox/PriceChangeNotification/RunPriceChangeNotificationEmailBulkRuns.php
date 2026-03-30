<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 16 Feb 2026 09:12:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\PriceChangeNotification;

use Lorisleiva\Actions\Concerns\AsAction;
use App\Services\QueryBuilder;
use App\Models\Comms\Outbox;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;

class RunPriceChangeNotificationEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:price-change-notification';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->whereIn('code', [OutboxCodeEnum::PRICE_CHANGE_NOTIFICATION]);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->whereNotNull('shop_id');
        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessPriceChangePerOutbox::run($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
