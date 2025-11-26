<?php

/*
 * Author: Vika Aqordi
 * Created on 26-11-2025-15h-12m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\CRM\Customer;



use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Models\Ordering\Order;

class PayOrderWithCustomerBalance extends OrgAction
{
    use WithActionUpdate;
    public function handle(Order $order)
    {
        dd($order);
    }

    public function asController(Order $order)
    {
        $this->initialisationFromShop($order->shop, []);
        $this->handle($order);
    }
}
