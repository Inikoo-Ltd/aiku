<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class PayOrder extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $payment = StorePayment::make()->action($order->customer, $paymentAccount, $modelData);

        AttachPaymentToOrder::make()->action($order, $payment, []);

        return $payment;
    }

    public function rules(): array
    {
        return [
            'amount'       => ['required', 'decimal:0,2'],
            'reference'    => ['nullable', 'string', 'max:255'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, PaymentAccount $paymentAccount, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $paymentAccount, $this->validatedData);
    }


    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
