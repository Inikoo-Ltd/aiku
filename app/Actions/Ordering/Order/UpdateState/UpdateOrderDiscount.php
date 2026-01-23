<?php

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\OrgAction;
use App\Actions\Ordering\Transaction\UpdateTransactionDiscretionaryDiscount;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderDiscount extends OrgAction
{
    public function handle(Order $order, array $modelData): Order
    {
        return DB::transaction(function () use ($order, $modelData) {
            $discountPercentage = $modelData['discretionary_discount_percentage'] ?? 0;

            // Loop through all product transactions in the order
            foreach ($order->transactions as $transaction) {
                // Only apply discount to product transactions (those with an asset_id)
                if ($transaction->asset_id) {

                    // Option 1: Call the transaction update action directly if it's implemented
                    // UpdateTransactionDiscretionaryDiscount::run($transaction, [
                    //    'discretionary_discount_percentage' => $discountPercentage
                    // ]);

                    // Option 2 (Manual Implementation for now as per request context):
                    // Update the transaction data directly here
                    $data = $transaction->data ?? [];
                    $data['discretionary_discount_percentage'] = $discountPercentage;

                    // You might need to recalculate net_amount here depending on your system's logic
                    // For now, we just save the percentage as requested

                    $transaction->update(['data' => $data]);
                }
            }

            // Optionally recalculate order totals here if needed
            // $order->refresh();

            return $order;
        });
    }

    public function rules(): array
    {
        return [
            'discretionary_discount_percentage' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function asController(Organisation $organisation, Order $order, ActionRequest $request): Order
    {
        $this->initialisation($organisation, $request);

        return $this->handle($order, $this->validatedData);
    }

    public function htmlResponse(Order $order): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Order discount updated successfully.'),
        ]);

        return;
    }
}
