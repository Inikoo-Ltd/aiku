<?php

/*
 * author Arya Permana - Kirin
 * created on 15-04-2025-16h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\ImportTransactionInOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class ImportRetinaOrderTransaction extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order, array $modelData): Order
    {
        ImportTransactionInOrder::make()->action($order, $modelData);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'             => ['required', 'file', 'mimes:xlsx,csv,xls,txt'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
