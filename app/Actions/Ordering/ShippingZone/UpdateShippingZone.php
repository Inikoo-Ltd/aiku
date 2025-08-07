<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 10:48:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\ShippingZone;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Ordering\ShippingZoneResource;
use App\Models\Ordering\ShippingZone;
use App\Rules\IUnique;
use Lorisleiva\Actions\ActionRequest;

class UpdateShippingZone extends OrgAction
{
    use WithActionUpdate;


    private ShippingZone $shippingZone;

    public function handle(ShippingZone $shippingZone, array $modelData): ShippingZone
    {
        return $this->update($shippingZone, $modelData, ['territories', 'price']);
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'sometimes',
                new IUnique(
                    table: 'shipping_zones',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'value' => $this->shippingZone->id, 'operator' => '!='],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
                'between:2,16',
                'alpha_dash'
            ],
            'name'        => ['sometimes', 'max:250', 'string'],
            'status'      => ['sometimes', 'required', 'boolean'],
            'price'       => ['sometimes', 'array'],
            'territories' => ['sometimes', 'array'],
            'position'    => ['required', 'integer'],
            'is_failover' => ['sometimes', 'boolean'],

        ];

        if (!$this->strict) {
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(ShippingZone $shippingZone, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): ShippingZone
    {
        if (!$audit) {
            ShippingZone::disableAuditing();
        }
        $this->strict       = $strict;
        $this->asAction     = true;
        $this->shippingZone = $shippingZone;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shippingZone->shop, $modelData);

        return $this->handle($shippingZone, $this->validatedData);
    }

    public function asController(ShippingZone $shippingZone, ActionRequest $request): ShippingZone
    {
        $this->shippingZone = $shippingZone;
        $this->initialisationFromShop($shippingZone->shop, $request);

        return $this->handle($shippingZone, $this->validatedData);
    }


    public function jsonResponse(ShippingZone $shippingZone): ShippingZoneResource
    {
        return new ShippingZoneResource($shippingZone);
    }
}
