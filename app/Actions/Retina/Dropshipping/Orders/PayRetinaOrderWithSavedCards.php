<?php
/*
 * author Arya Permana - Kirin
 * created on 02-07-2025-17h-39m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\RetinaAction;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\MitSavedCard;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class PayRetinaOrderWithSavedCards extends RetinaAction
{
    use WithBasketStateWarning;
    use WithRetinaOrderPlacedRedirection;

    /**
     * @throws \Throwable
     */
    public function handle(Order $order)
    {
        $customer = $order->customer;

        if($customer->balance >= $order->total_amount) {
            PayRetinaOrderWithBalance::run($order);
        } else {
            foreach ($customer->mitSavedCard->sortBy('priority') as $card) {
                $this->processSavedCard($order,$card);
            }
        }
    }

    public function processSavedCard(Order $order,MitSavedCard $mitSavedCard)
    {
        dd('not available yet');
    }

}
