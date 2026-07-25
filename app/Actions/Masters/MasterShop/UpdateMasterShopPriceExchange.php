<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Jul 2026 15:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterShop;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterShop;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterShopPriceExchange extends OrgAction
{
    use WithMastersEditAuthorisation;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(MasterShop $masterShop, array $modelData): MasterShop
    {
        $currencyCode   = $modelData['currency'];
        $priceExchanges = $masterShop->price_exchanges ?? [];

        if ($modelData['is_major']) {
            $priceExchanges[$currencyCode] = ['is_major' => true];
        } else {
            $majorCurrencyCode = $modelData['major'];

            if (!data_get($priceExchanges, "$majorCurrencyCode.is_major")) {
                throw ValidationException::withMessages([
                    'major' => __(':currency is not a major currency of this master shop', ['currency' => $majorCurrencyCode])
                ]);
            }

            $followers = collect($priceExchanges)
                ->filter(fn (array $exchangeData) => !($exchangeData['is_major'] ?? false) && ($exchangeData['major'] ?? null) === $currencyCode)
                ->keys();

            if ($followers->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'currency' => __('Cannot make :currency minor, these currencies follow it: :followers', [
                        'currency'  => $currencyCode,
                        'followers' => $followers->join(', ')
                    ])
                ]);
            }

            $priceExchanges[$currencyCode] = [
                'is_major' => false,
                'major'    => $majorCurrencyCode,
                'exchange' => $modelData['exchange'],
            ];
        }

        $masterShop->update(['price_exchanges' => $priceExchanges]);

        if (!$modelData['is_major']) {
            RecalculateMasterShopMinorCurrencyPrices::dispatch($masterShop, $currencyCode);
        }

        return $masterShop;
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'is_major' => ['required', 'boolean'],
            'major'    => ['required_if:is_major,false', 'string', 'size:3', 'different:currency'],
            'exchange' => ['required_if:is_major,false', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(MasterShop $masterShop, ActionRequest $request): MasterShop
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(MasterShop $masterShop, array $modelData): MasterShop
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterShop->group, $modelData);

        return $this->handle($masterShop, $this->validatedData);
    }
}
