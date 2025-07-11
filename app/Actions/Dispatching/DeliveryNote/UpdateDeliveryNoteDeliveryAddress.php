<?php
/*
 * author Arya Permana - Kirin
 * created on 11-07-2025-11h-07m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\Helpers\Address\UpdateAddress;
use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\ActionRequest;

class UpdateDeliveryNoteDeliveryAddress extends OrgAction
{
    public function handle(DeliveryNote $deliveryNote, array $modelData): void
    {
        UpdateAddress::run($deliveryNote->deliveryAddress, $modelData);
    }

    public function rules(): array
    {
        return [
            'address'             => ['sometimes'],
        ];
    }

    public function asController(DeliveryNote $deliveryNote, ActionRequest $request): void
    {
        $this->initialisationFromWarehouse($deliveryNote->warehouse, $request);

        $this->handle($deliveryNote, $this->validatedData);
    }
}
