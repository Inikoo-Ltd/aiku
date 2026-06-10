<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\AddVoucherToOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaOrderVoucher extends RetinaAction
{
    public function handle(Order $order, array $modelData): void
    {
        AddVoucherToOrder::run($order,$modelData);

        $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'voucher' => ['required', 'string', 'max:32']
        ];
    }


    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($order, $this->validatedData);
    }
}
