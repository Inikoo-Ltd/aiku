<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 25 Aug 2025 12:11:37 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RefundPayment extends OrgAction
{
    use AsAction;

    public function handle(Payment $payment): Payment
    {
        $modelData = [
            'type' => PaymentTypeEnum::REFUND,
            'original_payment_id' => $payment->id,
            'amount' => $payment->amount
        ];

        return StorePayment::make()->action($payment->customer, $payment->paymentAccount, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request);

        return $this->handle($payment);
    }

}
