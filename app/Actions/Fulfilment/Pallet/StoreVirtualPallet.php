<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 10:42:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\OrgAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Location;
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreVirtualPallet extends OrgAction
{
    private FulfilmentCustomer $fulfilmentCustomer;

    /**
     * @throws \Throwable
     */
    public function handle(FulfilmentCustomer $fulfilmentCustomer, array $modelData): Pallet
    {
        /** @var Location $location */
        $location = Location::findOrFail(Arr::get($modelData, 'location_id'));

        if (blank(Arr::get($modelData, 'customer_reference'))) {
            data_set($modelData, 'customer_reference', 'FLOOR-'.$location->code);
        }

        data_set($modelData, 'is_virtual', true);
        data_set($modelData, 'warehouse_id', $location->warehouse_id);
        data_set($modelData, 'state', PalletStateEnum::STORING);
        data_set($modelData, 'status', PalletStatusEnum::STORING);
        data_set($modelData, 'received_at', now());
        data_set($modelData, 'booked_in_at', now());
        data_set($modelData, 'storing_at', now());

        return StorePallet::make()->action($fulfilmentCustomer, $modelData, strict: false);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        return [
            'location_id'        => [
                'required',
                'integer',
                Rule::exists('locations', 'id')
                    ->where('organisation_id', $this->organisation->id)
                    ->where('allow_fulfilment', true),
                new IUnique(
                    table: 'pallets',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->fulfilmentCustomer->id],
                        ['column' => 'is_virtual', 'value' => true],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ],
                    message: __('This customer already has a virtual pallet in this location.'),
                    caseSensitive: false
                ),
            ],
            'customer_reference' => ['sometimes', 'nullable', 'string', 'max:64'],
            'notes'              => ['sometimes', 'nullable', 'string', 'max:16384'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): Pallet
    {
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $request);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function action(FulfilmentCustomer $fulfilmentCustomer, array $modelData): Pallet
    {
        $this->asAction           = true;
        $this->fulfilmentCustomer = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilmentCustomer->fulfilment, $modelData);

        return $this->handle($fulfilmentCustomer, $this->validatedData);
    }

    public function htmlResponse(Pallet $pallet, ActionRequest $request): RedirectResponse
    {
        return Redirect::back();
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
