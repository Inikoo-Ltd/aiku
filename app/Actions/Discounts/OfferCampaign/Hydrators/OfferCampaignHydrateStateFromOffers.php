<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 15:41:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\Hydrators;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Traits\WithEnumStats;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferCampaign\OfferCampaignStateEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferCampaignHydrateStateFromOffers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferCampaign $offerCampaign): string
    {
        return $offerCampaign->id;
    }

    public function handle(OfferCampaign $offerCampaign): void
    {

        $hasActive = false;
        $hasFinished = false;
        $hasSuspended = false;

        foreach ($offerCampaign->offers as $offer) {
            if ($offer->state == OfferStateEnum::ACTIVE) {
                $hasActive = true;
            }
            if ($offer->state == OfferStateEnum::FINISHED) {
                $hasFinished = true;
            }
            if ($offer->state == OfferStateEnum::SUSPENDED) {
                $hasSuspended = true;
            }

        }

        $state = OfferCampaignStateEnum::IN_PROCESS;
        $status = false;
        if ($hasActive) {
            $state = OfferCampaignStateEnum::ACTIVE;
            $status = true;
        } elseif ($hasFinished) {
            $state = OfferCampaignStateEnum::FINISHED;
        } elseif ($hasSuspended) {
            $state = OfferCampaignStateEnum::SUSPENDED;
        }

        $modelData = [
            'state' => $state,
            'status' => $status
        ];

        if (!$offerCampaign->start_at &&  $state == OfferCampaignStateEnum::ACTIVE) {
            $modelData['start_at'] = $offerCampaign->offers()->min('start_at') ?? now();
        }

        if (!$offerCampaign->finish_at && $state == OfferCampaignStateEnum::FINISHED) {
            $modelData['finish_at'] = $offerCampaign->offers()->max('end_at') ?? now();
        }

        $offerCampaign->update($modelData);
        ShopHydrateOffersData::run($offerCampaign->shop_id);

    }

    public function getCommandSignature(): string
    {
        return 'offer_campaign:hydrate_state_from_offers {offer_campaign?}';
    }

    public function asCommand(Command $command): int
    {
        if ($command->argument('offer_campaign')) {
            $offerCampaign = OfferCampaign::findOrFail($command->argument('offer_campaign'));
            $this->handle($offerCampaign);
            $command->info("Hydrated offer campaign $offerCampaign->code");

            return 0;
        }

        foreach (OfferCampaign::all() as $offerCampaign) {
            $this->handle($offerCampaign);
            $command->info("Hydrated offer campaign $offerCampaign->code");
        }
        return 0;

    }


}
