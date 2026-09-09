<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Nov 2024 21:11:58 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop;

use App\Actions\OrgAction;
use App\Actions\Traits\WithStoreModelAddress;
use App\Models\Catalogue\Shop;
use App\Rules\ValidAddress;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class StoreShopAddress extends OrgAction
{
    use WithStoreModelAddress;


    public function handle(Shop $shop, array $modelData): Shop
    {
        $type = Arr::get($modelData, 'type');

        $addressData = $modelData['address']->toArray();
        data_set($addressData, 'group_id', $shop->group_id);
        $address = $this->storeModelAddress($addressData);


        $shop->updateQuietly(
            [
                $type == 'legal' ? 'address_id' : 'collection_address_id' => $address->id,
            ]
        );

        return $shop;
    }

    public function rules(): array
    {
        return [

            'address' => ['required', new ValidAddress(requireFullAddress: !$this->asAction)],
            'type'    => ['required', Rule::in(['legal', 'collection'])],
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $audit = true): Shop
    {
        if (!$audit) {
            Shop::disableAuditing();
        }
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }


}
