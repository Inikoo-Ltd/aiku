<?php

/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-09h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\TopUp;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\RetinaAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\TopUp;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTopUp extends RetinaAction
{
    use WithNoStrictRules;

    public $commandSignature = 'make:topup {customer} {amount}';

    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): TopUp
    {
        return DB::transaction(function () use ($customer, $paymentAccount, $modelData) {
            $payment = StorePayment::make()->action($customer, $paymentAccount, [
                'amount' => Arr::get($modelData, 'amount'),
                'type'   => PaymentTypeEnum::PAYMENT,
            ]);

            return StoreTopUp::make()->action($payment, [
                'amount' => Arr::get($modelData, 'amount'),
            ]);
        });
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
        ];
    }

    public function asController(PaymentAccount $paymentAccount, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $paymentAccount, $this->validatedData);
    }

    public function asCommand(Command $command)
    {
        $customer = Customer::where('email', $command->argument('customer'))->first();
        $paymentAccount = PaymentAccount::where('code', PaymentAccountTypeEnum::PAYPAL->value)->first();

        return $this->handle($customer, $paymentAccount, [
            'amount' => $command->argument('amount')
        ]);
    }
}
