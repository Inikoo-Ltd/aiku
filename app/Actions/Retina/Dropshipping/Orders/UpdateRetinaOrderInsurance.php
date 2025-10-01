<?php
namespace App\Actions\Retina\Dropshipping\Orders;
/*
 * Author: Vika Aqordi
 * Created on: 01-10-2025-11h-46m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

use App\Actions\Ordering\Order\UpdateOrderPremiumDispatch;
use App\Actions\RetinaAction;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaOrderInsurance extends RetinaAction
{
    public function handle(Order $order, array $modelData): void
    {
        dd('TODO: create file UpdateOrderInsurance');
        // UpdateOrderPremiumDispatch::run($order, $modelData);  TODO: create file UpdateOrderInsurance
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order->customer_id == $this->customer->id;
    }

    public function rules(): array
    {
        return [
            'has_insurance' => ['required', 'boolean'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($order, $this->validatedData);
    }
}
