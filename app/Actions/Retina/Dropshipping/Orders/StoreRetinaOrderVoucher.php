<?php

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\AddVoucherToOrder;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaOrderVoucher extends RetinaAction
{
    public function handle(Order $order, array $modelData): array
    {

        return AddVoucherToOrder::run($order, $modelData['voucher']);


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

    protected function prepareForValidation(): void
    {
        if ($this->has('voucher')) {
            $this->set(
                'voucher',
                Str::lower(trim($this->get('voucher')))
            );
        }
    }

    public function asController(Order $order, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
