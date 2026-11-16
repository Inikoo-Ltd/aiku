<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\UpdateDeliveryNoteDeliveryAddress;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Helpers\Country;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobileDeliveryNoteDeliveryAddress
{
    use AsController;
    use WithMobilePickingGuards;

    private const array CONTACT_FIELDS = ['contact_name', 'company_name', 'email', 'phone'];

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
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanShip($deliveryNote, $user);

        if (!$deliveryNote->isDeliveryAddressEditable()) {
            throw ValidationException::withMessages([
                'address' => __('The delivery address can only be changed before a shipment is created and while the delivery note is not dispatched or cancelled.'),
            ]);
        }

        $contactFields = array_values(array_intersect(self::CONTACT_FIELDS, array_keys($request->all())));
        abort_if($contactFields && !$deliveryNote->canBeWorkedOnBy($user), 403, __('Only warehouse staff can change the contact details of this delivery note.'));

        $data = Validator::make($request->all(), [
            'address'                     => ['required', 'array'],
            'address.country_code'        => ['required', 'string', 'size:2'],
            'address.address_line_1'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.address_line_2'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.sorting_code'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.postal_code'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.locality'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.dependent_locality'  => ['sometimes', 'nullable', 'string', 'max:255'],
            'address.administrative_area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_name'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'company_name'                => ['sometimes', 'nullable', 'string', 'max:255'],
            'email'                       => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone'                       => ['sometimes', 'nullable', 'string', 'max:255'],
        ])->validate();

        $address = $this->address($data['address']);

        DB::transaction(function () use ($deliveryNote, $data, $contactFields, $address) {
            if ($contactFields) {
                UpdateDeliveryNote::make()->action($deliveryNote, Arr::only($data, $contactFields));
            }

            UpdateDeliveryNoteDeliveryAddress::make()->handle($deliveryNote->refresh(), ['address' => $address]);
        });

        return $this->respondWith($deliveryNote, $user, 'done', __('Delivery address saved.'));
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

        Validator::make(['address' => $address], UpdateDeliveryNoteDeliveryAddress::make()->rules())->validate();

        return $address;
    }
}
