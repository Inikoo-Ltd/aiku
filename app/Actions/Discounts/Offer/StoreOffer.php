<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 13:16:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffers;
use App\Actions\Discounts\Offer\Search\OfferRecordSearch;
use App\Actions\Discounts\OfferAllowance\StoreOfferAllowance;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Models\SysAdmin\Organisation;
use App\Models\Catalogue\Shop;
use Lorisleiva\Actions\ActionRequest;

class StoreOffer extends OrgAction
{
    use WithNoStrictRules;
    use WithStoreOffer;

    /**
     * @throws \Throwable
     */
    public function handle(OfferCampaign $offerCampaign, array $modelData): Offer
    {
        $modelData  = $this->prepareOfferData($offerCampaign, $modelData);
        $allowances = Arr::pull($modelData, 'allowances', []);
        $offer      = DB::transaction(function () use ($offerCampaign, $modelData, $allowances) {
            /** @var Offer $offer */
            $offer = $offerCampaign->offers()->create($modelData);
            $offer->stats()->create();
            foreach ($allowances as $allowanceData) {
                data_set($allowanceData, 'duration', $offer->duration);
                data_set($allowanceData, 'end_at', null);
                StoreOfferAllowance::run($offer, $allowanceData);
            }
            UpdateOfferAllowanceSignature::run($offer);

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $offer->timeSeries()->create(
                    [
                        'frequency' => $frequency,
                    ]
                );
            }

            return $offer;
        });
        GroupHydrateOffers::dispatch($offerCampaign->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOffers::dispatch($offerCampaign->organisation)->delay($this->hydratorsDelay);
        ShopHydrateOffers::dispatch($offerCampaign->shop)->delay($this->hydratorsDelay);
        OfferCampaignHydrateOffers::dispatch($offerCampaign)->delay($this->hydratorsDelay);
        OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);

        return $offer;
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        return $this->action(
            $offerCampaign,
            $this->validatedData
        );
    }

    public function inOfferCampaign(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);

        dd($this->validatedData);
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
            'label'        => ['sometimes', 'nullable', 'max:1028', 'string'],
            'data'         => ['sometimes', 'required'],
            'settings'     => ['sometimes', 'required'],
            'trigger_data' => ['sometimes', 'required'],
            'start_at'     => ['sometimes', 'date'],
            'end_at'       => ['sometimes', 'nullable', 'date'],
            'type'         => ['required', 'string'],
            'offer_qty_items'     => ['sometimes', 'integer'],
            'offer_amount'        => ['sometimes', 'decimal:0,2'],
            'discount_percentage' => ['sometimes', 'integer', 'between:0,100'],
            'trigger_type' => ['sometimes', Rule::in(['Order'])],
            'allowances'   => ['sometimes', 'nullable', 'array'],
            'duration'     => ['sometimes', Rule::enum(OfferDurationEnum::class)],
        ];
        if (!$this->strict) {
            $rules['start_at']         = ['sometimes', 'nullable', 'date'];
            $rules['finish_at']        = ['sometimes', 'nullable', 'date'];
            $rules['state']            = ['sometimes', Rule::enum(OfferStateEnum::class)];
            $rules['is_discretionary'] = ['sometimes', 'boolean'];
            $rules['is_locked']        = ['sometimes', 'boolean'];
            $rules['source_data']      = ['sometimes', 'array'];
            $rules['label']            = ['sometimes', 'max:1028', 'string'];
            $rules                     = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(OfferCampaign $offerCampaign, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Offer
    {
        if (!$audit) {
            Offer::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($offerCampaign->shop, $modelData);

        return $this->handle($offerCampaign, $this->validatedData);
    }
}
