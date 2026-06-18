<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jun 2026 12:58:50 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class ActivateScheduledOffers
{
    use AsAction;

    public function handle(): void
    {
        //Find all scheduled offers
        $scheduledOffers = Offer::where('state', OfferStateEnum::IN_PROCESS)
            ->whereNull('source_id')//Do not touch old aurora offers
            ->where('start_at', '<=', now())->get();

        foreach ($scheduledOffers as $offer) {
            ActivateOffer::run($offer);
        }
    }

    public string $commandSignature = 'activate:scheduled_offers';

    public function asCommand(Command $command)
    {
        $this->handle();
        $command->info('Ran!');
    }
}
