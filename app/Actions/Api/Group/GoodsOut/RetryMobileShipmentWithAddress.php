<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\SaveDeliveryNoteShippingFieldsAndRetryStoreShipping;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\User;
use App\Rules\ValidAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RetryMobileShipmentWithAddress
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobileShipments;

    private const array ADDRESS_FIELDS = [
        'address_line_1',
        'address_line_2',
        'sorting_code',
        'postal_code',
        'locality',
        'dependent_locality',
        'administrative_area',
    ];

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanShip($deliveryNote, $user);
        $this->ensureActionAvailable($deliveryNote, $user, 'add_shipment');

        $data = Validator::make($request->all(), [
            'shipper_id'                  => ['required', 'integer'],
            'contact_name'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                       => ['sometimes', 'nullable', 'string'],
            'phone'                       => ['sometimes', 'nullable', 'string'],
            'address'                     => ['sometimes', 'array'],
            'address.country_code'        => ['required_with:address', 'string', 'size:2'],
            'address.address_line_1'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.address_line_2'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.sorting_code'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.postal_code'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.locality'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.dependent_locality'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.administrative_area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'override_lock'               => ['sometimes', 'nullable', 'boolean'],
        ])->validate();

        $shipper   = $this->storableShipper($deliveryNote, (int) $data['shipper_id'], (bool) ($data['override_lock'] ?? false));
        $modelData = Arr::only($data, ['contact_name', 'company_name', 'email', 'phone']);

        if (isset($data['address'])) {
            $modelData['address'] = $this->address($data['address']);
        }

        $stateBefore = $deliveryNote->state;

        $this->passingShipmentRefusals(fn () => SaveDeliveryNoteShippingFieldsAndRetryStoreShipping::make()->handle($deliveryNote, $shipper, $modelData));

        return $this->respondWith($deliveryNote, $user, 'done', $this->shipmentAddedMessage($deliveryNote, $stateBefore));
    }

    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function address(array $address): array
    {
        $countryId = Country::where('code', strtoupper($address['country_code']))->value('id');

        if (!$countryId) {
            throw ValidationException::withMessages(['address' => __('Unknown country :code.', ['code' => $address['country_code']])]);
        }

        $address = [...Arr::only($address, self::ADDRESS_FIELDS), 'country_id' => $countryId];

        Validator::make(['address' => $address], ['address' => [new ValidAddress()]])->validate();

        return $address;
    }
}
