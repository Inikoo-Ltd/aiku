<?php

/*
 * author Louis Perez
 * created on 04-02-2026-11h-56m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Ordering\Order;

use App\Actions\Helpers\TaxCategory\GetTaxCategory;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Contracts\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderReCalculateVAT extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;

    private Order $order;

    public function handle(Order $order): Order
    {
        $customer = $order->customer;
        $order->update([
            'tax_category_id' => GetTaxCategory::run(
                country: $order->organisation->country,
                taxNumber: $customer->taxNumber,
                billingAddress: $order->billingAddress,
                deliveryAddress: $order->deliveryAddress,
                isRe: $customer->is_re,
            )->id,
        ]);
        CalculateOrderTotalAmounts::run($order, false, false);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if (in_array($this->order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED])) {
            $validator->errors()->add('message', __('Unable to re-calculate VAT Charge on a closed order.'));
        }
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
