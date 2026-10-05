<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Mon, 21 Sept 2026, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Outbox\FavouritesOnOffer;

use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Comms\Outbox;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

class RunFavouritesOnOfferEmailBulkRuns
{
    use AsAction;

    public string $commandSignature = 'run:favourites-on-offer-notification';
    public string $jobQueue = 'ses';

    public function handle(): void
    {
        $queryOutbox = QueryBuilder::for(Outbox::class);
        $queryOutbox->where('code', OutboxCodeEnum::FAVOURITES_ON_OFFER);
        $queryOutbox->where('state', OutboxStateEnum::ACTIVE);
        $queryOutbox->where('is_applicable', true);
        $queryOutbox->whereNotNull('shop_id');

        $queryOutbox->select('outboxes.id', 'outboxes.shop_id', 'outboxes.code', 'outboxes.last_sent_at');
        $outboxes = $queryOutbox->get();

        /** @var Outbox $outbox */
        foreach ($outboxes as $outbox) {
            ProcessFavouritesOnOfferPerOutbox::dispatch($outbox);
        }
    }

    public function asCommand(): void
    {
        $this->run();
    }
}
