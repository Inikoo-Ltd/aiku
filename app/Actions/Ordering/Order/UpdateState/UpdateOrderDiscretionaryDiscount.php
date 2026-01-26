<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 23 Jan 2026 17:04:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Support\Arr;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;

class UpdateOrderDiscretionaryDiscount extends OrgAction
{
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): Order
    {
        if (in_array($order->state, [
            OrderStateEnum::DISPATCHED,
            OrderStateEnum::FINALISED,
            OrderStateEnum::CANCELLED,
        ])) {
            abort(403);
        }

        if (Arr::get($modelData, 'discretionary_offer') == 0) {
            $modelData['discretionary_offer'] = null;
        }

        return DB::transaction(function () use ($order, $modelData) {
            foreach ($order->transactions as $transaction) {
                if ($transaction->model_type == 'Product') {
                    $transaction->update($modelData);
                }
            }

            OrderHydrateDiscretionaryOffersData::run($order);
            CalculateOrderDiscounts::run($order);

            return $order;
        });
    }

    public function rules(): array
    {
        return [
            'discretionary_offer'       => ['nullable', 'numeric', 'between:0,1'],
            'discretionary_offer_label' => ['sometimes', 'nullable', 'string', 'max:255']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('discretionary_offer', $request->input('discretionary_offer') / 100);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }

    public function htmlResponse(Order $order): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Order discount updated successfully for all items.'),
        ]);
    }
}
