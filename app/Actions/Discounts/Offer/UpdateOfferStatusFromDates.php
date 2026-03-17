<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026 09:50:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffersData;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffersState;
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;

class UpdateOfferStatusFromDates extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        $currentStatus = $offer->status;
        $currentState  = $offer->state;


        if ($offer->duration == OfferDurationEnum::PERMANENT) {
            if ($offer->start_at->gte(now())) {
                $statusFromDates = true;
                $stateFromDates  = OfferStateEnum::ACTIVE;
            } else {
                $statusFromDates = false;
                $stateFromDates  = OfferStateEnum::IN_PROCESS;
            }
        } else {
            list($statusFromDates, $stateFromDates) = $this->getStateFromDates($offer);
        }

        if ($currentState == OfferStateEnum::SUSPENDED && $stateFromDates != OfferStateEnum::FINISHED) {
            $status = false;
            $state  = OfferStateEnum::SUSPENDED;
        } else {
            $status = $statusFromDates;
            $state  = $stateFromDates;
        }


        $offer->update(
            [

                'state'  => $state,
                'status' => (bool)$status
            ]
        );
        $offer->refresh();
        foreach ($offer->offerAllowances as $offerAllowance) {
            $currentOfferAllowanceState = $offerAllowance->state;

            if ($currentOfferAllowanceState == OfferAllowanceStateEnum::SUSPENDED && $stateFromDates != OfferStateEnum::FINISHED) {
                $status = false;
                $state  = OfferAllowanceStateEnum::SUSPENDED;
            } else {
                $status = $statusFromDates;
                $state  = $stateFromDates->value;
            }


            $offerAllowance->update([
                'state'  => $state,
                'status' => (bool)$status
            ]);
        }

        if ($currentStatus != $offer->status || $currentState != $offer->state) {
            OfferCampaignHydrateOffersState::run($offer->offerCampaign);
            ShopHydrateOffersData::run($offer->shop_id);
        }
        if ($currentStatus != $offer->status) {
            RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop_id)->delay(now()->addSeconds(10));
        }


        return $offer;
    }


    public function getStateFromDates($offer): array
    {


        if ($offer->start_at->lte(now()) && $offer->end_at->gt(now())) {
            return [
                true,
                OfferStateEnum::ACTIVE,
            ];
        }

        if ($offer->end_at->lte(now())) {
            return [
                false,
                OfferStateEnum::FINISHED,
            ];
        }

        return [
            false,
            OfferStateEnum::IN_PROCESS,
        ];
    }


    public function getCommandSignature(): string
    {
        return 'offer:update_status_from_dates {offer?}';
    }

    public function asCommand(Command $command): int
    {
        if (!$command->argument('offer')) {
            $offer = Offer::where('slug', $command->argument('offer'))->firstOrFail();

            $this->handle($offer);
            return 0;
        }

        $aikuShops = Shop::where('is_aiku', true)->pluck('id')->toArray();

        foreach (Offer::whereIn('shop_id', $aikuShops)->where('duration',OfferDurationEnum::INTERVAL)->get() as $offer) {
            $this->handle($offer);
        }


        return 0;
    }


}
