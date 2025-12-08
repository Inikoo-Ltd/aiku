<?php

/*
 * author Arya Permana - Kirin
 * created on 17-01-2025-09h-12m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet;

use App\Actions\Fulfilment\Pallet\UpdatePallet;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Models\Fulfilment\Pallet;
use App\Rules\IUnique;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPallet extends RetinaAction
{
    private Pallet $pallet;

    public function handle(Pallet $pallet, array $modelData): Pallet
    {
        return UpdatePallet::run($pallet, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->fulfilmentCustomer->id == $request->route()->parameter('pallet')->fulfilment_customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        return [
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
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->pallet->id,
                        ],
                    ]
                ),

            ],
            'state' => [
                'sometimes',
                Rule::enum(PalletStateEnum::class),
            ],
            'status' => [
                'sometimes',
                Rule::enum(PalletStatusEnum::class),
            ],
            'type' => [
                'sometimes',
                Rule::enum(PalletTypeEnum::class),
            ],
            'rental_id' => [
                'nullable',
                Rule::Exists('rentals', 'id')->where('fulfilment_id', $this->fulfilment->id),
            ],
            'pallet_return_id' => [
                'sometimes',
                'nullable',
                Rule::Exists('pallet_returns', 'id')->where('fulfilment_id', $this->fulfilment->id),

            ],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1024'],
            'received_at' => ['sometimes', 'nullable', 'date'],
            'booked_in_at' => ['sometimes', 'nullable', 'date'],
            'storing_at' => ['sometimes', 'nullable', 'date'],
            'reference' => [
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
                            'column' => 'id',
                            'operator' => '!=',
                            'value' => $this->pallet->id,
                        ],
                    ]
                ),
            ],

        ];
    }

    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->pallet = $pallet;
        $this->initialisation($request);

        return $this->handle($pallet, $this->validatedData);
    }

    public function action(Pallet $pallet, array $modelData): Pallet
    {
        $this->pallet = $pallet;
        $this->asAction = true;
        $this->initialisationFulfilmentActions($pallet->fulfilmentCustomer, $modelData);

        return $this->handle($pallet, $this->validatedData);
    }

    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }
}
