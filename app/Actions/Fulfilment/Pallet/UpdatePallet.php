<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\SetPalletDeliveryAutoServices;
use App\Actions\Fulfilment\PalletReturn\AutomaticallySetPalletReturnAsPickedIfAllItemsPicked;
use App\Actions\Inventory\Location\Hydrators\LocationHydratePallets;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePallets;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePallets;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePallet extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private Pallet $pallet;

    public function handle(Pallet $pallet, array $modelData, bool $hydrateParents = true): Pallet
    {
        $originalType = $pallet->type;
        $pallet       = $this->update($pallet, $modelData, ['data']);
        $pallet->refresh();


        $oldLocation = $pallet->location;

        if (Arr::hasAny($pallet->getChanges(), ['state'])) {

            if ($pallet->pallet_return_id && $hydrateParents) {
                AutomaticallySetPalletReturnAsPickedIfAllItemsPicked::run($pallet->palletReturn);
            }

            GroupHydratePallets::dispatch($pallet->group)->delay($this->hydratorsDelay);
            OrganisationHydratePallets::dispatch($pallet->organisation)->delay($this->hydratorsDelay);
            FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer)->delay($this->hydratorsDelay);
            FulfilmentHydratePallets::dispatch($pallet->fulfilment)->delay($this->hydratorsDelay);
            WarehouseHydratePallets::dispatch($pallet->warehouse)->delay($this->hydratorsDelay);

        }

        if (Arr::hasAny($pallet->getChanges(), ['state','location_id'])) {
            if ($oldLocation) {
                LocationHydratePallets::dispatch($oldLocation)->delay($this->hydratorsDelay); //Hydrate Old Location
            }
            if ($pallet->location) {
                LocationHydratePallets::dispatch($pallet->location)->delay($this->hydratorsDelay); //Hydrate New Location
            }
        }


        if ($originalType !== $pallet->type) {
            SetPalletDeliveryAutoServices::run($pallet->palletDelivery);
        }
        PalletRecordSearch::dispatch($pallet);

        return $pallet->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'customer_reference'      => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'pallets',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->pallet->fulfilmentCustomer->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->pallet->id
                        ],
                    ]
                ),


            ],
            'state'                   => [
                'sometimes',
                Rule::enum(PalletStateEnum::class)
            ],
            'status'                  => [
                'sometimes',
                Rule::enum(PalletStatusEnum::class)
            ],
            'type'                    => [
                'sometimes',
                Rule::enum(PalletTypeEnum::class)
            ],
            'rental_id'               => [
                'nullable',
                Rule::Exists('rentals', 'id')->where('fulfilment_id', $this->fulfilment->id)
            ],
            'pallet_return_id'        => [
                'sometimes',
                'nullable',
                Rule::Exists('pallet_returns', 'id')->where('fulfilment_id', $this->fulfilment->id)

            ],
            'location_id'             => ['sometimes', 'nullable', Rule::exists('locations', 'id')],
            'notes'                   => ['sometimes', 'nullable', 'string', 'max:16384'],
            'received_at'             => ['sometimes', 'nullable', 'date'],
            'booked_in_at'            => ['sometimes', 'nullable', 'date'],
            'storing_at'              => ['sometimes', 'nullable', 'date'],
            'dispatched_at'           => ['sometimes', 'nullable', 'date'],
            'picking_at'              => ['sometimes', 'nullable', 'date'],
            'picked_at'               => ['sometimes', 'nullable', 'date'],
            'reference'               => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'pallets',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->pallet->fulfilmentCustomer->id],
                        [
                            'column'   => 'id',
                            'operator' => '!=',
                            'value'    => $this->pallet->id
                        ],
                    ]
                ),
            ],
            'requested_for_return_at' => ['sometimes', 'nullable', 'date'],
        ];
        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function fromRetina(Pallet $pallet, ActionRequest $request): Pallet
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;
        $this->pallet       = $pallet;

        $this->initialisation($request->get('website')->organisation, $request);

        return $this->handle($pallet, $this->validatedData);
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisationFromFulfilment($pallet->fulfilment, $request);

        return $this->handle($pallet, $this->validatedData);
    }

    public function action(Pallet $pallet, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Pallet
    {
        $this->strict = $strict;
        if (!$audit) {
            Pallet::disableAuditing();
        }

        $this->pallet         = $pallet;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromFulfilment($pallet->fulfilment, $modelData);

        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
