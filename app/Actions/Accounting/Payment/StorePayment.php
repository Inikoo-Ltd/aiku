<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Payment\Traits\HydratesPaymentSideEffects;
use App\Actions\Accounting\Traits\AuthorizesAccountingEdit;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\OrgAction;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
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
    use HydratesPaymentSideEffects;
    use AuthorizesAccountingEdit;

    public string $commandSignature = 'payment:create {customer} {paymentAccount} {scope}';

    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        data_set($modelData, 'date', now(), overwrite: false);
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'org_payment_service_provider_id', $paymentAccount->org_payment_service_provider_id);
        data_set($modelData, 'payment_service_provider_id', $paymentAccount->payment_service_provider_id);
        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'currency_id', $customer->shop->currency_id);
        data_set($modelData, 'org_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->currency), overwrite: false);
        data_set($modelData, 'grp_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->group->currency), overwrite: false);

        $source    = Arr::pull($modelData, 'source');
        $modelData = array_merge($modelData, self::methodFromSource($source, $paymentAccount));

        /** @var Payment $payment */
        $payment = $paymentAccount->payments()->create($modelData);

        $this->hydratePaymentSideEffects($payment);

        if ($payment->status == PaymentStatusEnum::SUCCESS) {
            PaymentAccountHydrateCustomers::dispatch($paymentAccount)->delay(2);
        }

        return $payment;
    }

    /**
     * checkout.com describes the instrument in source: type (card, klarna, paypal, ideal...),
     * card_wallet_type for Apple/Google Pay and scheme (VISA, MASTERCARD) for cards. The wallet or
     * type is the method staff care about; the scheme is kept as sub_method. Without a source the
     * payment account type is the best that is known.
     *
     * @return array{method: string, sub_method: string|null}
     */
    public static function methodFromSource(?array $source, PaymentAccount $paymentAccount): array
    {
        $method    = Arr::get($source, 'card_wallet_type') ?: Arr::get($source, 'type') ?: $paymentAccount->type->value;
        $subMethod = Arr::get($source, 'scheme') ?: null;

        return [
            'method'     => strtolower(trim($method)),
            'sub_method' => $subMethod ? strtolower(trim($subMethod)) : null,
        ];
    }

    public function prepareForValidation(): void
    {
        /** Normalise float arithmetic dirt (e.g. 0.039999999999999 from decimal subtraction)
         * to cents once at the money boundary, so the decimal:0,2 rule only rejects
         * genuinely malformed amounts */
        if (is_numeric($this->get('amount'))) {
            $this->set('amount', round((float)$this->get('amount'), 2));
        }
    }

    public function rules(): array
    {
        $rules = [
            'reference'               => ['nullable', 'string', 'max:255'],
            'amount'                  => ['required', 'decimal:0,2'],
            'data'                    => ['sometimes', 'array'],
            'date'                    => ['sometimes', 'date'],
            'status'                  => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'                   => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
            'type'                    => ['sometimes', 'required', Rule::enum(PaymentTypeEnum::class)],
            'original_payment_id'     => [
                'sometimes',
                'nullable',
                'integer',
                'exists:payments,id'
            ],
            'payment_account_shop_id' => [
                'sometimes',
                'integer',
            ],
            'api_point_type'          => [
                'sometimes',
                'string',
            ],
            'api_point_id'            => [
                'sometimes',
                'integer',
            ],
            'is_mit'                  => [
                'sometimes',
                'nullable',
                'boolean',
            ],
            'debug_mit_status'        => [
                'sometimes',
                'nullable',
                'string',
            ],
            'debug_mit_is_approved'   => [
                'sometimes',
                'nullable',
                'boolean',
            ],
            'source'                  => ['sometimes', 'array'],
        ];

        if (!$this->strict) {
            $rules['org_amount']   = ['sometimes', 'numeric'];
            $rules['grp_amount']   = ['sometimes', 'numeric'];
            $rules['source_id']    = ['sometimes', 'string'];
            $rules['cancelled_at'] = ['sometimes', 'nullable', 'date'];
            $rules['completed_at'] = ['sometimes', 'nullable', 'date'];
            $rules['created_at']   = ['sometimes', 'date'];
            $rules['fetched_at']   = ['sometimes', 'date'];
        }

        return $rules;
    }


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


    public function asController(Customer $customer, PaymentAccount $paymentAccount, ActionRequest $request, int $hydratorsDelay = 0): void
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $request);

        $this->handle($customer, $paymentAccount, $this->validatedData);
    }

}
