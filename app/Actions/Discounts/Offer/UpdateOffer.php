<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 14:52:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffers;
use App\Actions\Discounts\Offer\Search\OfferRecordSearch;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Discounts\Offer;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateOffer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Offer $offer;

    public function handle(Offer $offer, array $modelData): Offer
    {
        $offer = $this->update($offer, $modelData);

        OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);

        if ($offer->wasChanged(['state', 'status'])) {
            GroupHydrateOffers::dispatch($offer->group)->delay($this->hydratorsDelay);
            OrganisationHydrateOffers::dispatch($offer->organisation)->delay($this->hydratorsDelay);
            ShopHydrateOffers::dispatch($offer->shop)->delay($this->hydratorsDelay);
            OfferCampaignHydrateOffers::dispatch($offer->offerCampaign)->delay($this->hydratorsDelay);
        }

        return $offer;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("discounts.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'code'         => [
                'sometimes',
                new IUnique(
                    table: 'offers',
                    extraConditions: [
                        [
                            'column' => 'shop_id',
                            'value'  => $this->shop->id,
                        ],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->offer->id
                        ]
                    ]
                ),

                'max:64',
                'alpha_dash'
            ],
            'name'         => ['sometimes', 'max:250', 'string'],
            'data'         => ['sometimes', 'required'],
            'settings'     => ['sometimes', 'required'],
            'trigger_data' => ['sometimes', 'required'],
            'start_at'     => ['sometimes', 'date'],
            'end_at'       => ['sometimes', 'nullable', 'date'],
        ];

        if (!$this->strict) {
            $rules             = $this->noStrictUpdateRules($rules);
            $rules['start_at'] = ['sometimes', 'nullable', 'date'];
        }

        return $rules;
    }


    public function action(Offer $offer, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Offer
    {
        if (!$audit) {
            Offer::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->offer          = $offer;
        $this->initialisationFromShop($offer->shop, $modelData);

        return $this->handle($offer, $this->validatedData);
    }

    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->offer = $offer;
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer, $this->validatedData);
    }

    public function jsonResponse(Offer $offer): OfferResource
    {
        return new OfferResource($offer);
    }
}
