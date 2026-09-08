<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 12:58:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ActivateScheduledOffers
{
    use AsAction;

    public function handle(): void
    {
        foreach (UpdateOfferStatusFromDates::make()->outOfSyncOffers()->get() as $offer) {
            UpdateOfferStatusFromDates::run($offer);
        }
    }

    public string $commandSignature = 'activate:scheduled_offers';

    public function asCommand(Command $command): void
    {
        $this->handle();
        $command->info('Ran!');
    }
}
