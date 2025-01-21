<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydratePallets;
use App\Actions\Fulfilment\FulfilmentCustomer\Hydrators\FulfilmentCustomerHydratePallets;
use App\Actions\Fulfilment\Pallet\Search\PalletRecordSearch;
use App\Actions\Fulfilment\PalletDelivery\AutoAssignServicesToPalletDelivery;
use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDeliveryStateFromItems;
use App\Actions\Inventory\Warehouse\Hydrators\WarehouseHydratePallets;
use App\Actions\RetinaAction;
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

class UpdateRetinaPallet extends RetinaAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    private Pallet $pallet;
    private bool $action = false;

    public function handle(Pallet $pallet, array $modelData): Pallet
    {
        $originalType = $pallet->type;
        $pallet       = $this->update($pallet, $modelData, ['data']);


        if (Arr::hasAny($pallet->getChanges(), ['state'])) {
            if ($pallet->pallet_delivery_id) {
                UpdatePalletDeliveryStateFromItems::run($pallet->palletDelivery);
            }


            GroupHydratePallets::dispatch($pallet->group)->delay($this->hydratorsDelay);
            OrganisationHydratePallets::dispatch($pallet->organisation)->delay($this->hydratorsDelay);
            FulfilmentCustomerHydratePallets::dispatch($pallet->fulfilmentCustomer)->delay($this->hydratorsDelay);
            FulfilmentHydratePallets::dispatch($pallet->fulfilment)->delay($this->hydratorsDelay);
            WarehouseHydratePallets::dispatch($pallet->warehouse)->delay($this->hydratorsDelay);
        }

        if ($originalType !== $pallet->type) {
            AutoAssignServicesToPalletDelivery::run($pallet->palletDelivery, $pallet, $originalType);
        }
        PalletRecordSearch::dispatch($pallet);

        return $pallet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->action) {
            return true;
        }

        if ($request->user() instanceof WebUser) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        $rules = [
            'customer_reference' => [
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
            'state'              => [
                'sometimes',
                Rule::enum(PalletStateEnum::class)
            ],
            'status'             => [
                'sometimes',
                Rule::enum(PalletStatusEnum::class)
            ],
            'type'               => [
                'sometimes',
                Rule::enum(PalletTypeEnum::class)
            ],
            'rental_id'          => [
                'nullable',
                Rule::Exists('rentals', 'id')->where('fulfilment_id', $this->fulfilment->id)
            ],
            'pallet_return_id'   => [
                'sometimes',
                'nullable',
                Rule::Exists('pallet_returns', 'id')->where('fulfilment_id', $this->fulfilment->id)

            ],
            'notes'              => ['sometimes','nullable', 'string', 'max:1024'],
            'received_at'        => ['sometimes','nullable',  'date'],
            'booked_in_at'       => ['sometimes', 'nullable', 'date'],
            'storing_at'         => ['sometimes', 'nullable', 'date'],
            'reference'          => [
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

        ];
        if (!$this->strict) {
            $rules                 = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */
        $fulfilmentCustomer = $request->user()->customer->fulfilmentCustomer;
        $this->fulfilment   = $fulfilmentCustomer->fulfilment;
        $this->pallet       = $pallet;

        $this->initialisation($request);

        return $this->handle($pallet, $this->validatedData);
    }

    public function action(Pallet $pallet, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Pallet
    {
        $this->pallet         = $pallet;
        $this->action         = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFulfilmentActions($pallet->fulfilmentCustomer, $modelData);

        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
