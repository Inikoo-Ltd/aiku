<?php

namespace App\Actions\Ordering\Order;

use App\Models\Ordering\Order;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class AddVoucherToOrder
{
    use AsAction;

    public function handle(Order $order, array $modelData): void
    {
    }

    public function asController(Order $order, Request $request)
    {
        dd($request->all());
    }
}
