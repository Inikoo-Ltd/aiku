<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\AddVoucherToOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaOrderVoucher extends RetinaAction
{
    public function handle(Order $order, array $modelData): array
    {
        return AddVoucherToOrder::run($order,$modelData);
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


    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
