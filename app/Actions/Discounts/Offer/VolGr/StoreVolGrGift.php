<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 17:54:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Discounts\Offer\ActivateOffer;
use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVolGrGift extends OrgAction
{
    use AsAction;

    public function handle(OfferCampaign $offerCampaign, $modelData): Offer
    {

        if ($offerId = Arr::get($offerCampaign->data, 'vol_gr_gift_offer_id')) {
            $offer = Offer::find($offerId);
            if ($offer) {
                return UpdateVolGrGift::run($offer, $modelData);
            }
        }

        $offerData = [];
        data_set($offerData, 'duration', OfferDurationEnum::PERMANENT);
        data_set($offerData, 'start_at', now());
        data_set($offerData, 'type', 'VolGr Gift');
        data_set($offerData, 'code', 'vol-gr-gift-'.$offerCampaign->shop->slug);
        data_set($offerData, 'name', 'GR gift');
        data_set($offerData, 'trigger_type', 'Customer');

        data_set(
            $offerData,
            'trigger_data',
            [
                'min_amount' => Arr::pull($modelData, 'amount'),
            ]
        );

        $allowanceData = [
            'products' => $modelData['products'],
            'default'  => Arr::get($modelData, 'default'),
        ];

        data_set(
            $offerData,
            'allowances',
            [
                [
                    'class'       => OfferAllowanceClass::GIFT,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type'        => OfferAllowanceType::GIFT->value,
                    'data'        => $allowanceData
                ]
            ]
        );
        $offer = StoreOffer::run($offerCampaign, $offerData);
        ActivateOffer::run($offer);

        $data = $offerCampaign->data;
        data_set($data, 'vol_gr_gift_offer_id', $offer->id);
        $offerCampaign->update(['data' => $data]);

        return $offer;
    }

    public function rules(): array
    {
        return [
            'amount'   => ['numeric', 'required'],
            'products' => ['required', 'array'],
            'default'  => ['sometimes', 'nullable', 'integer']
        ];
    }

    public function asController(OfferCampaign $offerCampaign, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offerCampaign->shop, $request);

        return $this->handle($offerCampaign, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
