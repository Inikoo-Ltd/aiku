<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:19:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment;

use App\Actions\Accounting\Payment\Hydrators\PaymentHydrateUniversalSearch;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Accounting\PaymentGateway\Checkout\Channels\Checkout;
use App\Actions\Accounting\PaymentGateway\Xendit\Channels\Invoice\MakePaymentUsingInvoice;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Market\Shop\Hydrators\ShopHydratePayments;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\PaymentServiceProvider\PaymentServiceProviderEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\CRM\Customer;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsCommand;

class StorePayment extends OrgAction
{
    use AsCommand;

    public string $commandSignature = 'payment:create {customer} {paymentAccount}';

    public function handle(Customer $customer, PaymentAccount $paymentAccount, array $modelData): Payment
    {
        data_set($modelData, 'date', gmdate('Y-m-d H:i:s'), overwrite: false);


        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'payment_service_provider_id', $paymentAccount->payment_service_provider_id);
        data_set($modelData, 'customer_id', $customer->id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_fill($modelData, 'currency_id', $customer->shop->currency_id);


        data_set($modelData, 'org_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->currency), overwrite: false);
        data_set($modelData, 'group_amount', Arr::get($modelData, 'amount') * GetCurrencyExchange::run($customer->shop->currency, $paymentAccount->organisation->group->currency), overwrite: false);

        /** @var Payment $payment */
        $payment = $paymentAccount->payments()->create($modelData);

        match ($paymentAccount->paymentServiceProvider->code) {
            PaymentServiceProviderEnum::CHECKOUT->value => Checkout::run($payment, $modelData),
            PaymentServiceProviderEnum::XENDIT->value   => MakePaymentUsingInvoice::run($payment)
        };

        // GroupHydratePayments::dispatch($payment->group)->delay($this->hydratorsDelay);
        // OrganisationHydratePayments::dispatch($paymentAccount->organisation)->delay($this->hydratorsDelay);
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

        return $request->user()->hasPermissionTo("accounting.edit");
    }

    public function rules(): array
    {
        return [
            'reference'    => ['nullable', 'string', 'max:255'],
            'amount'       => ['required', 'decimal:0,2'],
            'org_amount'   => ['sometimes', 'numeric'],
            'group_amount' => ['sometimes', 'numeric'],
            'data'         => ['sometimes', 'array'],
            'currency_id'  => ['required', 'exists:currencies,id'],
            'date'         => ['sometimes', 'date'],
            'created_at'   => ['sometimes', 'date'],
            'completed_at' => ['sometimes', 'nullable', 'date'],
            'cancelled_at' => ['sometimes', 'nullable', 'date'],
            'status'       => ['sometimes', 'required', Rule::enum(PaymentStatusEnum::class)],
            'state'        => ['sometimes', 'required', Rule::enum(PaymentStateEnum::class)],
            'source_id'    => ['sometimes', 'string']
        ];
    }

    public function action(Customer $customer, PaymentAccount $paymentAccount, array $modelData, int $hydratorsDelay = 0): Payment
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $paymentAccount, $this->validatedData);
    }

    public function asCommand(Command $command): int
    {
        $customer       = Customer::where('slug', $command->argument('customer'))->first();
        $paymentAccount = PaymentAccount::where('slug', $command->argument('paymentAccount'))->first();

        $modelData = [
            'reference'   => rand(),
            'currency_id' => 1,
            'amount'      => 100,
            'data'        => [
                'card_name'         => 'Raul Inikoo',
                'card_number'       => '4485040371536584',
                'card_expiry_year'  => '2045',
                'card_expiry_month' => '02',
                'card_cvv'          => '000',
            ]
        ];

        $this->handle($customer, $paymentAccount, $modelData);

        return 0;
    }
}
