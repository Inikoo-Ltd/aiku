<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-10h-24m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Order;

use App\Actions\Api\Retina\Fulfilment\Resource\PalletReturnApiResource;
use App\Actions\Retina\Fulfilment\PalletReturn\UpdateRetinaPalletReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateApiOrder extends RetinaApiAction
{
    private PalletReturn $palletReturn;

    public function handle(PalletReturn $palletReturn, array $modelData): PalletReturn|JsonResponse
    {
        if ($palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            return response()->json([
                'message' => 'This PalletReturn is already in the "' . $palletReturn->state->value . '" state and cannot be updated.',
            ]);
        }
        $palletReturn = UpdateRetinaPalletReturn::make()->action($palletReturn, $modelData);

        return $palletReturn;
    }

    public function rules(): array
    {
        return [
            'customer_reference'        => ['sometimes', 'nullable', 'string', Rule::unique('pallet_returns', 'customer_reference')
                ->ignore($this->palletReturn->id)],
            'estimated_delivery_date'   => ['sometimes', 'date'],
            'customer_notes'   => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function jsonResponse(PalletReturn $palletReturn)
    {
        return PalletReturnApiResource::make($palletReturn)
            ->additional([
                'message' => __('PalletReturn updated successfully'),
            ]);
    }

    public function asController(PalletReturn $palletReturn, ActionRequest $request): PalletReturn|JsonResponse
    {
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($request);

        return $this->handle($palletReturn, $this->validatedData);
    }
}
