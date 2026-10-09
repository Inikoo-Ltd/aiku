<?php

/*
 * author Louis Perez
 * created on 04-02-2026-11h-56m
 * GitHub: https://github.com/louis-perez
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
use Illuminate\Console\Command;
use Illuminate\Contracts\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\Authorisations\Ordering\WithOrderEditAuthorisation;

class UpdateOrderReCalculateVAT extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;
    use WithOrderEditAuthorisation;

    private Order $order;

    public function handle(Order $order): Order
    {
        if (!$order->canChangeTaxCategory()) {
            return $order;
        }

        $customer = $order->customer;

        $taxCategory = GetTaxCategory::run(
            country: $order->organisation->country,
            taxNumber: $customer->taxNumber,
            billingAddress: $order->billingAddress,
            deliveryAddress: $order->taxableDeliveryAddress($customer->taxNumber),
            isRe: $order->is_re,
        );


        $order->update([
            'tax_category_id' => $taxCategory->id,
        ]);
        CalculateOrderTotalAmounts::run($order, false, false);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if (in_array($this->order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::CANCELLED])) {
            $validator->errors()->add('message', __('Unable to re-calculate VAT Charge on a closed order.'));
        }

        if ($this->order->invoices()->count() > 0) {
            $validator->errors()->add('message', __('Unable to re-calculate VAT Charge on an invoiced order.'));
        }

    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }

    public function getCommandSignature(): string
    {
        return 'order:recalculate-vat {order}';
    }

    public function asCommand(Command $command): int
    {
        $order = Order::where('slug', $command->argument('order'))->firstOrFail();
        if (!$order->canChangeTaxCategory()) {
            $command->error("Order $order->slug is invoiced or closed, its VAT is not re-calculated");

            return 1;
        }
        $this->handle($order);
        return 0;
    }


}
