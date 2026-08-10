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
use App\Actions\Fulfilment\StoredItem\StoreStoredItemsToReturn;
use App\Actions\Retina\Fulfilment\StoredItem\AttachRetinaStoredItemToReturn;
use App\Actions\RetinaApiAction;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletStoredItem;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use League\Uri\Components\Port;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AttachApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    private PalletReturn $palletReturn;
    private PalletStoredItem $item;
    private null|\Eloquent|Model $storedItem;

    public function handle(PalletReturn $palletReturn, Portfolio $portfolio, array $modelData): PalletStoredItem
    {
        try {
            /** @var StoredItem $storedItem */
            $storedItem = $portfolio->item;

            if(!$storedItem) {
                throw ValidationException::withMessages(['message' => 'Item not found.']);
            }

            $palletStoredItem = $storedItem->palletStoredItems()->where('quantity', '>', 0)->first();

            AttachRetinaStoredItemToReturn::run($palletReturn, $palletStoredItem, $modelData);

            return $palletStoredItem;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'quantity_ordered'    => ['required', 'numeric', 'min:0','max:'.$this->storedItem->total_quantity],
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if(!$this->storedItem) {
            throw ValidationException::withMessages(['message' => 'Item not found.']);
        }

        if(!$this->storedItem instanceof StoredItem) {
            throw ValidationException::withMessages(['message' => 'Portfolio not found.']);
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->palletReturn->state != PalletReturnStateEnum::IN_PROCESS) {
            $validator->errors()->add('message', 'This Order is already in the "' . $this->palletReturn->state->value . '" state and cannot be updated.');
        }
    }


    public function asController(PalletReturn $palletReturn, Portfolio $portfolio, ActionRequest $request): PalletStoredItem
    {
        $this->storedItem = $portfolio->item;
        $this->palletReturn = $palletReturn;
        $this->initialisationFromFulfilment($request);

        return $this->handle($palletReturn, $portfolio, $this->validatedData);
    }

    public function jsonResponse(PalletStoredItem $palletStoredItem)
    {
        return SKUApiResource::make($palletStoredItem)
            ->additional([
                'message' => __('SKO added to Order successfully'),
            ]);
    }
}
