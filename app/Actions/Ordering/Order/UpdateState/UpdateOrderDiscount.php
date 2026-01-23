<?php

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use App\Actions\Ordering\Order\Hydrators\OrderHydrateDiscretionaryOffersData;
use App\Actions\Ordering\Order\CalculateOrderDiscounts;

class UpdateOrderDiscount extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Transaction $transaction;
    public function handle(Order $order, array $modelData): Order
    {
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

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->transaction = $order->transactions->first();
        $this->initialisationFromShop($this->transaction->shop, $request);
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
