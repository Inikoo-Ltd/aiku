<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 24 Jan 2024 14:58:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Http\Resources\Helpers\AddressResource;
use App\Http\Resources\Helpers\CountryResource;
use App\Http\Resources\Helpers\CurrencyResource;
use App\Http\Resources\Helpers\LanguageResource;
use App\Models\Fulfilment\Fulfilment;
use Illuminate\Http\Resources\Json\JsonResource;

class FulfilmentResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Fulfilment $fulfilment */
        $fulfilment = $this;

        return [
            'id' => $fulfilment->id,
            'slug' => $fulfilment->slug,
            'code' => $fulfilment->shop->code,
            'name' => $fulfilment->shop->name,
            'company_name' => $fulfilment->shop->company_name,
            'contact_name' => $fulfilment->shop->contact_name,
            'email' => $fulfilment->shop->email,
            'phone' => $fulfilment->shop->phone,
            'address' => AddressResource::make($fulfilment->shop->address),
            'state' => $fulfilment->shop->state,
            'country' => CountryResource::make($fulfilment->shop->country),
            'currency' => CurrencyResource::make($fulfilment->shop->currency),
            'language' => LanguageResource::make($fulfilment->shop->language),
            'settings' => $fulfilment->settings,
            'data' => $fulfilment->shop->data,
        ];
    }
}
