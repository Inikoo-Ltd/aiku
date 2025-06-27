<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-11h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Fulfilment\Transaction;

use App\Actions\Api\Retina\Fulfilment\Resource\SKUApiResource;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletStoredItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AttachApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PalletReturn $palletReturn, PalletStoredItem $sku, array $modelData): PalletStoredItem|JsonResponse
    {
        AttachStoredItemToReturn::run($palletReturn, $sku, $modelData);

        return $sku;
    }

    public function rules(): array
    {
        return [
            'quantity_ordered'    => ['required', 'numeric', 'min:0','max:'.$this->sku->quantity],
        ];

    }

    public function afterValidator(Validator $validator)
    {
        if ($this->palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            $validator->errors()->add('message', 'This Order is already in the "' . $this->palletReturn->state->value . '" state and cannot be updated.');
        }
    }


    public function asController(PalletReturn $palletReturn, PalletStoredItem $sku, ActionRequest $request): PalletStoredItem|JsonResponse
    {
        $this->sku = $sku;
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($request);

        return $this->handle($palletReturn, $sku, $this->validatedData);
    }

    public function jsonResponse(PalletStoredItem $sku)
    {
        return SKUApiResource::make($sku)
            ->additional([
                'message' => __('SKU added to Order successfully'),
            ]);
    }
}
