<?php

/*
 * Author: Vika Aqordi
 * Created on 27-11-2025-13h-55m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/


namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class PayRetinaOrderWithBalanceAfterSubmitted extends RetinaAction
{
    
    public function handle(Order $order, bool $submitOrder = true): array
    {
        dd('xxxxxx');
    }


}
