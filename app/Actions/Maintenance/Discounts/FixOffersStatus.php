<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 31 Dec 2025 17:20:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Discounts;

use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Lorisleiva\Actions\Concerns\AsAction;

class FixOffersStatus
{
    use AsAction;
    use WithOrganisationSource;

    public string $commandSignature = 'repair:offers_status';

    /**
     * @throws \Exception
     */
    public function asCommand(\Illuminate\Console\Command $command): void
    {
        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        $offers = Offer::whereIn('shop_id', $aikuShops)->get();

        $progressBar = $command->getOutput()->createProgressBar($offers->count());
        $progressBar->start();

        /** @var Offer $offer */
        foreach ($offers as $offer) {
            if ($offer->state != OfferStateEnum::ACTIVE) {
                $offer->update([
                    'status' => false,
                ]);

                foreach ($offer->offerAllowances as $offerAllowance) {
                    $offerAllowance->update([
                        'state'  => $offer->state->value,
                        'status' => $offer->status,
                    ]);
                }
            } else {
                if ($offer->end_at < now()) {
                    $offer->update([
                        'state'  => OfferStateEnum::FINISHED,
                        'status' => false,
                    ]);

                    foreach ($offer->offerAllowances as $offerAllowance) {
                        $offerAllowance->update([
                            'state'  => $offer->state->value,
                            'status' => $offer->status,
                            'end_at' => $offer->end_at
                        ]);
                    }
                } else {
                    $offer->update([
                        'status' => true,
                    ]);
                }
            }
            $progressBar->advance();
        }

        $progressBar->finish();
        $command->newLine();
    }


}
