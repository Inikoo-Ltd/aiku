<?php
/*
 * author Arya Permana - Kirin
 * created on 07-05-2025-09h-13m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\TopUp;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\TopUp\Search\TopUpRecordSearch;
use App\Actions\Accounting\TopUp\StoreTopUp;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTopUps;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\TopUp;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTopUp extends RetinaAction
{
    use WithNoStrictRules;

    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): TopUp
    {
        $topUp = DB::transaction(function () use ($customer, $paymentAccount, $modelData) {
            $payment = StorePayment::make()->action($customer, $paymentAccount, [
                'amount' => Arr::get($modelData, 'amount'),
                'type'   => PaymentTypeEnum::PAYMENT,
            ]);
    
            $topUp = StoreTopUp::make()->action($payment, [
                'amount' => Arr::get($modelData, 'amount'),
            ]);

            return $topUp;
        });

        return $topUp;
    }

    public function rules(): array
    {
        $rules = [
            'amount' => ['required', 'numeric'],
        ];
        
        return $rules;
    }

    public function asController(PaymentAccount $paymentAccount, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($this->customer, $paymentAccount, $this->validatedData);
    }
}
