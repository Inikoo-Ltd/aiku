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
        dd('Data',$modelData);

    }

    public function rules(): array
    {
        return [
            'discretionary_discount_percentage' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

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
