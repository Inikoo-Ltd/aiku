<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Actions\OrgAction;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePreferredShippings;
use App\Models\Catalogue\PreferredShipping;
use App\Models\Catalogue\Shop;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StorePreferredShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Shop $shop, array $modelData): PreferredShipping
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        /** @var PreferredShipping $preferredShipping */
        $preferredShipping = $shop->preferredShippings()->create($modelData);

        ShopHydratePreferredShippings::dispatch($shop)->delay($this->hydratorsDelay);

        return $preferredShipping;
    }

    public function rules(): array
    {
        return [
            'shipper_id' => [
                'required',
                'integer',
                Rule::exists('shippers', 'id')->where('organisation_id', $this->shop->organisation_id),
            ],
            'country_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('countries', 'id')->where('status', true),
            ],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'important' => ['sometimes', 'boolean'],
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $audit = true): PreferredShipping
    {
        if (!$audit) {
            PreferredShipping::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }
}
