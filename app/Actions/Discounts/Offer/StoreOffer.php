<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 13:16:09 Malaysia Time, Kuala Lumpur, Malaysia
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
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\Helpers\Query;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreOffer extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

    /**
     * @throws \Throwable
     */
    public function handle(OfferCampaign $offerCampaign, null|Shop|Product|ProductCategory|Customer|Query $trigger, array $modelData): Offer
    {

        $modelData = $this->prepareOfferData($offerCampaign, $trigger, $modelData);
        $offer = DB::transaction(function () use ($offerCampaign, $modelData) {
            /** @var Offer $offer */
            $offer = $offerCampaign->offers()->create($modelData);
            $offer->stats()->create();

            return $offer;
        });
        GroupHydrateOffers::dispatch($offerCampaign->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOffers::dispatch($offerCampaign->organisation)->delay($this->hydratorsDelay);
        ShopHydrateOffers::dispatch($offerCampaign->shop)->delay($this->hydratorsDelay);
        OfferCampaignHydrateOffers::dispatch($offerCampaign)->delay($this->hydratorsDelay);
        OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);

        return $offer;
    }

    public function rules(): array
    {
        $rules = [
            'code'         => [
                'required',
                new IUnique(
                    table: 'offers',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                    ]
                ),

                'max:64',
                'alpha_dash'
            ],
            'name'         => ['required', 'max:250', 'string'],
            'data'         => ['sometimes', 'required'],
            'settings'     => ['sometimes', 'required'],
            'trigger_data' => ['sometimes', 'required'],
            'start_at'     => ['sometimes', 'date'],
            'end_at'       => ['sometimes', 'nullable', 'date'],
            'type'         => ['required', 'string'],
            'trigger_type' => ['sometimes', Rule::in(['Order'])],
        ];
        if (!$this->strict) {
            $rules['start_at']  = ['sometimes', 'nullable', 'date'];
            $rules['finish_at'] = ['sometimes', 'nullable', 'date'];
            $rules['state']     = ['sometimes', Rule::enum(OfferStateEnum::class)];
            $rules['is_discretionary'] = ['sometimes', 'boolean'];
            $rules['is_locked'] = ['sometimes', 'boolean'];
            $rules['source_data'] = ['sometimes', 'array'];

            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(OfferCampaign $offerCampaign, null|Shop|Product|ProductCategory|Customer|Query $trigger, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Offer
    {
        if (!$audit) {
            Offer::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($offerCampaign->shop, $modelData);

        return $this->handle($offerCampaign, $trigger, $this->validatedData);
    }
}
