<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Mar 2026 09:50:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Discounts\Offer\StoreOffer;
use App\Actions\Discounts\Offer\UpdateOfferStatusFromDates;
use App\Actions\OrgAction;
use App\Enums\Discounts\Offer\OfferDurationEnum;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceClass;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceTargetTypeEnum;
use App\Enums\Discounts\OfferAllowance\OfferAllowanceType;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreGrAmnesty extends OrgAction
{
    use AsAction;

    public function handle(OfferCampaign $offerCampaign, $modelData): Offer
    {
        $startAt = Carbon::parse($modelData['start_at'])->format('Y-m-d');
        $endAt   = Carbon::parse($modelData['start_at'])->format('Y-m-d');

        $offerData = [];
        data_set($offerData, 'duration', OfferDurationEnum::INTERVAL);
        data_set($offerData, 'start_at', $startAt.' 00:00:00');
        data_set($offerData, 'end_at', $endAt.' 23:59:59');
        data_set($offerData, 'status', false);
        data_set($offerData, 'state', OfferStateEnum::IN_PROCESS);

        data_set($offerData, 'type', 'GR Amnesty');
        data_set($offerData, 'code', 'gr-amnesty-'.$offerCampaign->shop->slug);
        data_set($offerData, 'name', 'GR Amnesty');
        data_set($offerData, 'trigger_type', 'Customer');

        data_set(
            $offerData,
            'allowances',
            [
                [
                    'class' => OfferAllowanceClass::GR_Amnesty,
                    'target_type' => OfferAllowanceTargetTypeEnum::ORDER->value,
                    'type' => OfferAllowanceType::GIFT->value,
                ]
            ]
        );
        $offer = StoreOffer::run($offerCampaign, $offerData);


        $data = $offerCampaign->data;
        data_set($data, 'gr_amnesty_offer_id', $offer->id);
        $offerCampaign->update(['data' => $data]);

        UpdateOfferStatusFromDates::run($offer);

        return $offer;
    }

    public function rules(): array
    {
        return [
            'start_at' => ['required', 'date'],
            'end_at'   => ['required', 'date'],
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
