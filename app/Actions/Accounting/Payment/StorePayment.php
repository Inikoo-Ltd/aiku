<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Payment\Hydrators\PaymentHydrateUniversalSearch;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Accounting\PaymentGateway\Checkout\Channels\MakePaymentUsingCheckout;
use App\Actions\Accounting\PaymentGateway\Paypal\Orders\MakePaymentUsingPaypal;
use App\Actions\Accounting\PaymentGateway\Xendit\Channels\Invoice\MakePaymentUsingXendit;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StorePayment extends OrgAction
{
    use AsCommand;

    public string $commandSignature = 'payment:create {customer} {paymentAccount} {scope}';

    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        data_set($modelData, 'date', gmdate('Y-m-d H:i:s'), overwrite: false);


        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'org_payment_service_provider_id', $paymentAccount->org_payment_service_provider_id);
        data_set($modelData, 'payment_service_provider_id', $paymentAccount->payment_service_provider_id);
        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'currency_id', $customer->shop->currency_id);


        data_set($modelData, 'org_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->currency), overwrite: false);
        data_set($modelData, 'group_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->group->currency), overwrite: false);

        /** @var Payment $payment */
        $payment = $paymentAccount->payments()->create($modelData);


        if($this->strict) {
            match ($paymentAccount->type->value) {
                PaymentAccountTypeEnum::CHECKOUT->value => MakePaymentUsingCheckout::run($payment, $modelData),
                PaymentAccountTypeEnum::XENDIT->value   => MakePaymentUsingXendit::run($payment),
                PaymentAccountTypeEnum::PAYPAL->value   => MakePaymentUsingPaypal::run($payment, $modelData),
                default                                 => null
            };
        }


        GroupHydratePayments::dispatch($payment->group)->delay($this->hydratorsDelay);
        OrganisationHydratePayments::dispatch($paymentAccount->organisation)->delay($this->hydratorsDelay);
        PaymentServiceProviderHydratePayments::dispatch($payment->paymentAccount->paymentServiceProvider)->delay($this->hydratorsDelay);
        PaymentAccountHydratePayments::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        ShopHydratePayments::dispatch($payment->shop)->delay($this->hydratorsDelay);


        PaymentHydrateUniversalSearch::dispatch($payment);

        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->hasPermissionTo("accounting.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference'    => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'decimal:0,2'],
            'org_amount'   => ['sometimes', 'numeric'],
            'group_amount' => ['sometimes', 'numeric'],
            'data'         => ['sometimes', 'array'],
            'date'         => ['sometimes', 'date'],
            'created_at'   => ['sometimes', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
        ];

        if (!$this->strict) {
            $rules['source_id']    = ['sometimes', 'string'];
            $rules['cancelled_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(Customer $customer, PaymentAccount $paymentAccount, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Payment
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $paymentAccount, $this->validatedData);
    }

    public function asController(Customer $customer, PaymentAccount $paymentAccount, ActionRequest $request, int $hydratorsDelay = 0): void
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $paymentAccount, $this->validatedData);
    }

}
