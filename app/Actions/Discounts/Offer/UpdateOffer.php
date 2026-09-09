<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Sept 2024 14:52:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOffers;
use App\Actions\Discounts\Offer\Traits\HandlesOfferSideEffects;
use App\Actions\Discounts\OfferCampaign\Hydrators\OfferCampaignHydrateOffers;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithStoreOffer;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOffer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;
    use WithStoreOffer;
    use HandlesOfferSideEffects;

    private Offer $offer;

    public function handle(Offer $offer, array $modelData): Offer
    {
        $newTriggerData = null;
        if (isset($modelData['trigger_data_item_quantity'])) {
            $newTriggerData = array_merge(
                $offer->trigger_data,
                ['item_quantity' => $modelData['trigger_data_item_quantity']]
            );
            unset($modelData['trigger_data_item_quantity']);
            $modelData['trigger_data'] = $newTriggerData;
        }

        $allowancesChanged = false;
        if (isset($modelData['edit_offer_discount'])) {
            $percentageOff = Arr::get($modelData['edit_offer_discount'], 'percentage_off');
            unset($modelData['edit_offer_discount']);

            if (!empty($percentageOff)) {
                $percentageOff = ((float)$percentageOff) / 100;
                foreach ($offer->offerAllowances()->where('type', OfferAllowanceType::PERCENTAGE_OFF)->get() as $offerAllowance) {
                    $allowanceData = $offerAllowance->data;
                    data_set($allowanceData, 'percentage_off', $percentageOff);
                    $offerAllowance->update(['data' => $allowanceData]);
                    $allowancesChanged = $allowancesChanged || $offerAllowance->wasChanged('data');
                }
                UpdateOfferAllowanceSignature::run($offer);
            }
        }

        // Section: edit Trigger
        if (isset($modelData['edit_offer_trigger'])) {
            $editOffer = $modelData['edit_offer_trigger'];

            // Set to trigger_data.item_quantity
            if (isset($editOffer['trigger_item_quantity']) && $editOffer['trigger_item_quantity'] !== '') {
                $triggerData = $newTriggerData;

                // Make sure it is an array
                if (!is_array($triggerData)) {
                    $triggerData = [];
                }

                // Set or update item_quantity
                $triggerData['item_quantity'] = (int)$editOffer['trigger_item_quantity'];

                // Assign back (Laravel will re-encode it to JSON automatically)
                $newTriggerData = $triggerData;
            }

            // Set to trigger_data.min_amount
            if (isset($editOffer['trigger_min_amount']) && $editOffer['trigger_min_amount'] !== '') {
                $triggerData = $newTriggerData;

                // Make sure it is an array
                if (!is_array($triggerData)) {
                    $triggerData = [];
                }

                // Set or update min_amount
                $triggerData['min_amount'] = (int)$editOffer['trigger_min_amount'];

                // Assign back (Laravel will re-encode it to JSON automatically)
                $newTriggerData = $triggerData;
            }

            // Set to trigger_data.order_number
            if (isset($editOffer['trigger_order_number']) && $editOffer['trigger_order_number'] !== '') {
                $triggerData = $newTriggerData;

                // Make sure it is an array
                if (!is_array($triggerData)) {
                    $triggerData = [];
                }

                // Set or update order_number
                $triggerData['order_number'] = (int)$editOffer['trigger_order_number'];

                // Assign back (Laravel will re-encode it to JSON automatically)
                $newTriggerData = $triggerData;
            }

            // Remove edit_offer_trigger from modelData
            $modelData['trigger_data'] = $newTriggerData;
            unset($modelData['edit_offer_trigger']);
        }

        // Section: prepare Offer Date
        $modelData = $this->prepareOfferDate($offer, $modelData);

        $offer = $this->update($offer, $modelData);

        if ($offer->wasChanged(['start_at', 'end_at'])) {
            UpdateOfferStatusFromDates::run($offer);
        }

        if ($offer->wasChanged(['trigger_data']) || $allowancesChanged) {
            $this->handleOfferSideEffects($offer, $offer->status);
        } elseif ($offer->wasChanged(['label'])) {
            if ($offer->hydratesCatalogueOffersData()) {
                UpdateProductCategoryOffersData::run($offer);
            }
            $this->cleanWebpagesCache($offer);
        }


        if ($offer->wasChanged(['state', 'status'])) {
            GroupHydrateOffers::dispatch($offer->group)->delay($this->hydratorsDelay);
            OrganisationHydrateOffers::dispatch($offer->organisation)->delay($this->hydratorsDelay);
            ShopHydrateOffers::dispatch($offer->shop)->delay($this->hydratorsDelay);
            OfferCampaignHydrateOffers::dispatch($offer->offerCampaign)->delay($this->hydratorsDelay);
            $this->handleOfferSideEffects($offer);
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

    public function rules(ActionRequest $request): array
    {
        $rules = [
            'code'                       => [
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
            'name'                       => ['sometimes', 'max:250', 'string'],
            'label'                      => ['sometimes', 'max:1028', 'string'],
            'data'                       => ['sometimes', 'required'],
            'settings'                   => ['sometimes', 'required'],
            'trigger_data'               => ['sometimes', 'required'],
            'trigger_data_item_quantity' => ['sometimes', 'integer'],
            'start_at'                   => ['sometimes', 'date'],
            'end_at'                     => ['sometimes', 'nullable', 'date'],
            'edit_offer_trigger'         => ['sometimes', 'nullable'],
            'edit_offer_discount'        => ['sometimes', 'nullable']
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

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Offer $offer, ActionRequest $request): offer
    {
        $this->offer = $offer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offer, $this->validatedData);
    }

    public function jsonResponse(Offer $offer): OfferResource
    {
        return new OfferResource($offer);
    }
}
