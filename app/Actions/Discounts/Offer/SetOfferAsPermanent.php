<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Jan 2026 13:18:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffers;
use App\Actions\Discounts\Offer\Search\OfferRecordSearch;
use App\Actions\Discounts\OfferAllowance\SetOfferAllowanceAsPermanent;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateStateFromOffers;
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class SetOfferAsPermanent extends OrgAction
{
    use WithActionUpdate;


    public function handle(Offer $offer): Offer
    {
        $modelData = [
            'state'    => OfferStateEnum::ACTIVE,
            'status'   => true,
            'duration' => OfferDurationEnum::PERMANENT,
            'end_at'   => null,
        ];

        if (!$offer->start_at) {
            data_set($modelData, 'start_at', now());
        }


        foreach ($offer->offerAllowances as $offerAllowance) {
            SetOfferAllowanceAsPermanent::run($offerAllowance);
        }


        $offer->update($modelData);
        $offerCampaign = $offer->offerCampaign;
        GroupHydrateOffers::dispatch($offerCampaign->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOffers::dispatch($offerCampaign->organisation)->delay($this->hydratorsDelay);
        ShopHydrateOffers::dispatch($offerCampaign->shop)->delay($this->hydratorsDelay);
        OfferCampaignHydrateOffers::dispatch($offerCampaign)->delay($this->hydratorsDelay);
        OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);

        OfferCampaignHydrateStateFromOffers::run($offer->offerCampaign);
        RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop_id);

        return $offer;
    }


    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer);
    }

    public function getCommandSignature(): string
    {
        return 'discounts:offer:set-as-permanent {offer}';
    }

    public function asCommand(Command $command): int
    {
        $offer = Offer::where('slug', $command->argument('offer'))->firstOrFail();
        $this->handle($offer);

        return 0;
    }

}
