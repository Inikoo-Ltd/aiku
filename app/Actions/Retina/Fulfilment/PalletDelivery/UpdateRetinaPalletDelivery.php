<?php

/*
 * author Arya Permana - Kirin
 * created on 16-01-2025-13h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\PalletDelivery;

use App\Actions\Fulfilment\PalletDelivery\UpdatePalletDelivery;
use App\Actions\RetinaAction;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\PalletDelivery;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaPalletDelivery extends RetinaAction
{
    private PalletDelivery $palletDelivery;

    public function handle(PalletDelivery $palletDelivery, array $modelData): PalletDelivery
    {
        return UpdatePalletDelivery::run($palletDelivery, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        } elseif ($this->customer->id == $request->route()->parameter('palletDelivery')->fulfilmentCustomer->customer_id) {
            return true;
        }

        return false;
    }

    public function rules(): array
    {
        $rules = [];

        if (! request()->user() instanceof WebUser) {
            $rules = [
                'public_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
                'internal_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            ];
        }

        return [
            'customer_reference' => ['sometimes', 'nullable', 'string', Rule::unique('pallet_deliveries', 'customer_reference')
                ->ignore($this->palletDelivery->id)],
            'customer_notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'estimated_delivery_date' => ['sometimes', 'date'],
            'current_recurring_bill_id' => [
                'sometimes',
                'nullable',
                Rule::exists('recurring_bills', 'id')->where('fulfilment_id', $this->fulfilment->id),
            ],
            ...$rules,
        ];
    }

    public function asController(PalletDelivery $palletDelivery, ActionRequest $request): PalletDelivery
    {

        $this->palletDelivery = $palletDelivery;
        $this->initialisation($request);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function action(PalletDelivery $palletDelivery, $modelData): PalletDelivery
    {
        $this->asAction = true;
        $this->palletDelivery = $palletDelivery;
        $this->initialisationFulfilmentActions($palletDelivery->fulfilmentCustomer, $modelData);

        return $this->handle($palletDelivery, $this->validatedData);
    }

    public function htmlResponse()
    {
        return back();
    }
}
