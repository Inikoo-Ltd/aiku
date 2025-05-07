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
use App\Http\Resources\Fulfilment\RetinaTopupResources;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\TopUp;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
                'currency_code' => $customer->shop->currency->code,
                'type'   => PaymentTypeEnum::PAYMENT,
            ]);

            $topup = StoreTopUp::make()->action($payment, [
                'amount' => Arr::get($modelData, 'amount'),
                // This only for testing, we need to remove later
                'reference' => Str::random()
            ]);

            return $topup;
        });
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric'],
        ];
    }

    public function htmlResponse(TopUp $topUp): RetinaTopupResources
    {
        return RetinaTopupResources::make($topUp);
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

        $this->handle($customer, $paymentAccount, [
            'amount' => $command->argument('amount')
        ]);
    }
}
