<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZone;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Ordering\ShippingZoneResource;
use App\Models\Billables\ShippingZone;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateShippingZone extends OrgAction
{
    use WithActionUpdate;


    private ShippingZone $shippingZone;

    public function handle(ShippingZone $shippingZone, array $modelData): ShippingZone
    {
        // The zone edit screen sends both halves together so only one of them can survive
        if (Arr::has($modelData, 'pricing')) {
            $pricing   = Arr::pull($modelData, 'pricing');
            $modelData = array_merge($modelData, [
                'price'          => Arr::get($pricing, 'price'),
                'shippers_price' => Arr::get($pricing, 'shippers_price', []),
            ]);
        }

        return $this->update($shippingZone, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'code'                                => [
                'sometimes',
                new IUnique(
                    table: 'shipping_zones',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'id', 'value' => $this->shippingZone->id, 'operator' => '!='],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
                'between:2,16',
                'alpha_dash'
            ],
            'name'                                => ['sometimes', 'max:250', 'string'],
            'status'                              => ['sometimes', 'required', 'boolean'],
            'pricing'                             => ['sometimes', 'array'],
            'pricing.price'                       => ['sometimes', 'nullable', 'array'],
            'pricing.shippers_price'              => ['sometimes', 'nullable', 'array'],
            'pricing.shippers_price.*.shipper_id' => ['required', 'integer', 'distinct', Rule::exists('shippers', 'id')->where('organisation_id', $this->organisation->id)->where('status', true)],
            'pricing.shippers_price.*.type'       => ['required', Rule::in(['Step Order Items Net Amount', 'Step Order Estimated Weight', 'TBC'])],
            'pricing.shippers_price.*.steps'      => ['sometimes', 'array'],
            'price'                               => ['sometimes', 'array'],
            'shippers_price'                      => ['sometimes', 'nullable', 'array'],
            'shippers_price.*.shipper_id'         => ['required', 'integer', 'distinct', Rule::exists('shippers', 'id')->where('organisation_id', $this->organisation->id)->where('status', true)],
            'shippers_price.*.type'               => ['required', Rule::in(['Step Order Items Net Amount', 'Step Order Estimated Weight', 'TBC'])],
            'shippers_price.*.steps'              => ['sometimes', 'array'],
            'territories'                         => ['sometimes', 'array'],
            'territories.*.country_code'          => ['sometimes', 'string', 'exists:countries,code'],
            'territories.*.included_postal_codes' => ['sometimes', 'string', 'nullable'],
            'territories.*.excluded_postal_codes' => ['sometimes', 'string', 'nullable'],
            'position'                            => ['sometimes', 'integer'],
            'is_failover'                         => ['sometimes', 'boolean'],

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
