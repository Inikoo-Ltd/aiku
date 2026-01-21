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
use App\Actions\Ordering\Order\RecalculateShopTotalsOrdersInBasket;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOffers;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOffers;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateOffer extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Offer $offer;

    public function handle(Offer $offer, array $modelData): Offer
    {
        // dd($offer);
        $newTriggerData = null;
        if (isset($modelData['trigger_data_item_quantity'])) {
            $newTriggerData = array_merge(
                $offer->trigger_data,
                ['item_quantity' => $modelData['trigger_data_item_quantity']]
            );
            unset($modelData['trigger_data_item_quantity']);
            $modelData['trigger_data'] = $newTriggerData;
        }

        // Section: edit Discount
        if (isset($modelData['edit_offer_discount'])) {
            $editOfferDiscount = $modelData['edit_offer_discount'];

            // Set percentage_off to allowance_signature
            if (!empty($editOfferDiscount['percentage_off'])) {
                $percentage_off = ((float) $editOfferDiscount['percentage_off']) / 100; // Convert 25 → 0.25

                $signature = trim((string) $offer['allowance_signature']);

                // Try to replace existing percentage_off
                $newSignature = preg_replace(
                    '/(percentage_off:)[0-9.]+/',
                    '${1}' . $percentage_off,
                    $signature,
                    -1,
                    $count
                );

                // If percentage_off does not exist, append it
                if ($count === 0) {
                    // Remove trailing colon if any
                    $signature = rtrim($signature, ':');

                    if ($signature === '') {
                        // Signature is empty → don't prefix with colon
                        $newSignature = 'percentage_off:' . $percentage_off;
                    } else {
                        $newSignature = $signature . ':percentage_off:' . $percentage_off;
                    }
                }

                unset($modelData['edit_offer_discount']);
                $modelData['allowance_signature'] = $newSignature;
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
                $triggerData['item_quantity'] = (int) $editOffer['trigger_item_quantity'];

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
                $triggerData['min_amount'] = (int) $editOffer['trigger_min_amount'];

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
                $triggerData['order_number'] = (int) $editOffer['trigger_order_number'];

                // Assign back (Laravel will re-encode it to JSON automatically)
                $newTriggerData = $triggerData;
            }

            // Remove edit_offer_trigger from modelData
            $modelData['trigger_data'] = $newTriggerData;
            unset($modelData['edit_offer_trigger']);
        }
// dd($modelData);

        $offer = $this->update($offer, $modelData);

        if ($offer->wasChanged(['name'])) {
            RecalculateShopTotalsOrdersInBasket::dispatch($offer->shop->id);
        }

        if ($offer->wasChanged(['code', 'name'])) {
            OfferRecordSearch::dispatch($offer)->delay($this->hydratorsDelay);
        }

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
            'edit_offer_discount'         => ['sometimes', 'nullable']
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
