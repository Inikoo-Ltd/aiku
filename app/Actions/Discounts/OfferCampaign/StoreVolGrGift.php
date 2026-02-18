<?php

/*
 * Author: Vika Aqordi
 * Created on 13-02-2026-14h-52m
 * Github: https://github.com/aqordeon
 * Copyright: 2026
*/

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreVolGrGift extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(OfferCampaign $offerCampaign, $modelData): OfferCampaign
    {
        dd($modelData);
    }

    public function rules(): array
    {
        return [
            'amount'   => ['numeric', 'required'],
            'products' => ['required', 'array'],
            'default'  => ['required', 'nullable', 'integer']
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): OfferCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offerCampaign, $this->validatedData);
    }
}
