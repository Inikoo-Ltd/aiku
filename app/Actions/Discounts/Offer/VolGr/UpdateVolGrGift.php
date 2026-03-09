<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Mar 2026 17:54:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer\VolGr;

use App\Actions\Discounts\OfferAllowance\UpdateOfferAllowance;
use App\Actions\OrgAction;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateVolGrGift extends OrgAction
{
    use AsAction;

    public function handle(Offer $offer, $modelData): Offer
    {

        if (Arr::has($modelData, 'amount')) {
            data_set(
                $offerData,
                'trigger_data',
                [
                    'min_amount' => Arr::pull($modelData, 'amount'),
                ]
            );
        }


        if (Arr::hasAny($modelData, ['products','default'])) {
            /** @var OfferAllowance $offerAllowance */
            $offerAllowance = $offer->offerAllowances()->first();

            $allowanceData = $offerAllowance->data;
            if (Arr::has($allowanceData, 'products')) {
                $modelData['products'] = $allowanceData['products'];
            }
            if (Arr::has($allowanceData, 'default')) {
                $modelData['default'] = $allowanceData['default'];
            }

            UpdateOfferAllowance::run($offerAllowance, [
                'data' => $modelData,
            ]);

        }

        return $offer;
    }

    public function rules(): array
    {
        return [
            'amount'   => ['sometimes','numeric', 'required'],
            'products' => ['sometimes', 'array'],
            'default'  => ['sometimes', 'nullable', 'integer']
        ];
    }

    public function asController(Offer $offer, ActionRequest $request): Offer
    {
        $this->initialisationFromShop($offer->shop, $request);

        return $this->handle($offer, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
