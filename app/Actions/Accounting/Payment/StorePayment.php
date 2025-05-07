<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\OrgPaymentServiceProvider\Hydrators\OrgPaymentServiceProviderHydratePayments;
use App\Actions\Accounting\Payment\Search\PaymentRecordSearch;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Accounting\PaymentGateway\Checkout\Channels\MakePaymentUsingCheckout;
use App\Actions\Accounting\PaymentGateway\Paypal\Orders\MakePaymentUsingPaypal;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\CRM\Customer;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StorePayment extends OrgAction
{
    use AsCommand;

    public string $commandSignature = 'payment:create {customer} {paymentAccount} {scope}';

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        $currencyCode = Arr::pull($modelData, 'currency_code');
        $totalAmount  = Arr::pull($modelData, 'total_amount');

        data_set($modelData, 'date', gmdate('Y-m-d H:i:s'), overwrite: false);

        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'org_payment_service_provider_id', $paymentAccount->org_payment_service_provider_id);
        data_set($modelData, 'payment_service_provider_id', $paymentAccount->payment_service_provider_id);
        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'currency_id', $customer->shop->currency_id);


        data_set($modelData, 'org_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->currency), overwrite: false);
        data_set($modelData, 'grp_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->group->currency), overwrite: false);


        $payment = DB::transaction(function () use ($paymentAccount, $modelData, $currencyCode, $totalAmount) {
            /** @var Payment $payment */
            $payment = $paymentAccount->payments()->create($modelData);

            $paypalData = [
                'total_amount'  => $totalAmount,
                'currency_code' => $currencyCode,
            ];

            if ($this->strict) {
                $response = match ($paymentAccount->type->value) {
                    PaymentAccountTypeEnum::CHECKOUT->value => MakePaymentUsingCheckout::run($payment, $modelData),
                    PaymentAccountTypeEnum::PAYPAL->value => MakePaymentUsingPaypal::run($payment, $paypalData),
                    default => null
                };

                UpdatePayment::run($payment, [
                    'data' => $response
                ]);
            }

            $payment->refresh();

            return $payment;
        });


        GroupHydratePayments::dispatch($payment->group)->delay($this->hydratorsDelay);
        OrganisationHydratePayments::dispatch($paymentAccount->organisation)->delay($this->hydratorsDelay);
        PaymentServiceProviderHydratePayments::dispatch($payment->paymentAccount->paymentServiceProvider)->delay($this->hydratorsDelay);
        PaymentAccountHydratePayments::dispatch($payment->paymentAccount)->delay($this->hydratorsDelay);
        ShopHydratePayments::dispatch($payment->shop)->delay($this->hydratorsDelay);
        OrgPaymentServiceProviderHydratePayments::dispatch($payment->orgPaymentServiceProvider)->delay($this->hydratorsDelay);


        PaymentRecordSearch::dispatch($payment);

        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        $rules = [
            'reference'     => ['nullable', 'string', 'max:255'],
            'amount'        => ['required', 'decimal:0,2'],
            'total_amount'  => ['sometimes', 'decimal:0,2'],
            'org_amount'    => ['sometimes', 'numeric'],
            'grp_amount'  => ['sometimes', 'numeric'],
            'data'          => ['sometimes', 'array'],
            'date'          => ['sometimes', 'date'],
            'status'        => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'         => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
            'items'         => ['sometimes', 'array'],
            'currency_code' => ['sometimes', 'string'],
            'type' => ['sometimes', 'required', Rule::enum(PaymentTypeEnum::class)],
            'original_payment_id' => [
                'sometimes',
                'nullable',
                'exists:payments,id'
            ]
        ];

        if (!$this->strict) {
            $rules['source_id']    = ['sometimes', 'string'];
            $rules['cancelled_at'] = ['sometimes', 'nullable', 'date'];
            $rules['completed_at'] = ['sometimes', 'nullable', 'date'];
            $rules['created_at']   = ['sometimes', 'date'];
            $rules['fetched_at']   = ['sometimes', 'date'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Customer $customer, PaymentAccount $paymentAccount, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Payment
    {
        if (!$audit) {
            Customer::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $paymentAccount, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, PaymentAccount $paymentAccount, ActionRequest $request, int $hydratorsDelay = 0): void
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $paymentAccount, $this->validatedData);
    }

}
