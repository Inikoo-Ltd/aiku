<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaOrderVoucher extends RetinaAction
{
    public function handle(Order $order, array $modelData): Order
    {
        dd([
            'order_id'     => $order->id,
            'voucher_code' => $modelData['voucher_code'],
            'modelData'    => $modelData,
        ]);

        $order->update([
            'data' => array_merge($order->data ?? [], [
                'voucher_code' => $modelData['voucher_code'],
            ]),
        ]);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'voucher_code' => ['required', 'string', 'max:255'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
