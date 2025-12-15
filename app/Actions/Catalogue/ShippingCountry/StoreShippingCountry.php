<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:35:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShippingCountry;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateShippingCountries;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\ShippingCountry;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShippingCountry extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Shop $shop, array $modelData): ShippingCountry
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);


        /** @var ShippingCountry $shippingCountry */
        $shippingCountry = $shop->shippingCountries()->create($modelData);

        ShopHydrateShippingCountries::dispatch($shop)->delay($this->hydratorsDelay);

        return $shippingCountry;
    }

    public function asController(Shop $shop, ActionRequest $request): ShippingCountry
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'country_id' => [
                'required',
                'integer',
                Rule::exists('countries', 'id')->where('status', true),
                Rule::unique('shipping_countries', 'country_id')->where('shop_id', $this->shop->id)
            ]
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $audit = true): ShippingCountry
    {
        if (!$audit) {
            ShippingCountry::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
