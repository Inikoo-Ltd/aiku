<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData)
    {
        DB::transaction(function () use ($customer, $modelData) {
            $order = StoreOrder::make()->action($customer, []);

            foreach ($modelData['product_ids'] as $productId) {
                StoreTransaction::make()->action($order, $productId, [
                    'quantity_ordered' => 1
                ]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Customer $customer, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
