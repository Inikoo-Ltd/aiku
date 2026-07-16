<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Apr 2024 20:10:35 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrgPaymentServiceProvider;

use App\Actions\Accounting\PaymentAccount\StorePaymentAccount;
use App\Actions\OrgAction;
use App\Actions\UI\Accounting\Traits\HasPaymentAccountUpdateActions;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreOrgPaymentServiceProviderAccount extends OrgAction
{
    use HasPaymentAccountUpdateActions;

    public function handle(PaymentServiceProvider $paymentServiceProvider, Organisation $organisation, array $modelData): PaymentAccount
    {
        $provider                  = $paymentServiceProvider->code;
        $orgPaymentServiceProvider = StoreOrgPaymentServiceProvider::run($paymentServiceProvider, $organisation, Arr::only($modelData, ['code']));

        data_set($modelData, 'type', match ($provider) {
            'btree' => PaymentAccountTypeEnum::BRAINTREE_PAYPAL->value,
            default => $provider,
        });

        $paymentAccount = StorePaymentAccount::run($orgPaymentServiceProvider, Arr::only($modelData, ['code', 'name', 'type']));

        $paymentAccount = $this->paymentAccountUpdateActions($provider, $paymentAccount, $modelData);

        $paymentAccount->refresh();

        return $paymentAccount;
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
        return [
            'code' => [
                'required',
                'max:16',
                'alpha_dash',
                new IUnique(
                    table: 'payment_service_providers',
                    extraConditions: [
                        ['column' => 'group_id', 'value' => $this->organisation->group_id],
                    ]
                ),
            ],
            'source_id'                  => ['sometimes', 'string'],
            'name'                       => ['required', 'string'],
            'bank_name'                  => ['sometimes', 'string'],
            'bank_account_name'          => ['sometimes', 'string'],
            'bank_account_id'            => ['sometimes', 'string'],
            'bank_swift_code'            => ['sometimes', 'string'],
            'checkout_access_key'        => ['sometimes', 'string'],
            'checkout_secret_key'        => ['sometimes', 'string'],
            'checkout_channel_id'        => ['sometimes', 'string'],
            'paypal_client_id'           => ['sometimes', 'string'],
            'paypal_client_secret'       => ['sometimes', 'string'],
            'braintree_client_id'        => ['sometimes', 'string'],
            'braintree_client_secret'    => ['sometimes', 'string']
        ];
    }

    public function asController(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, ActionRequest $request): PaymentAccount
    {
        $this->asAction = true;
        $this->initialisation($organisation, $request);

        return $this->handle($paymentServiceProvider, $organisation, $this->validatedData);
    }

    public function action(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, array $modelData): PaymentAccount
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);

        return $this->handle($paymentServiceProvider, $organisation, $this->validatedData);
    }

    public function htmlResponse(PaymentAccount $paymentAccount): Response
    {
        return Inertia::location(route('grp.org.accounting.payment-accounts.show', [
            'organisation'   => $paymentAccount->organisation->slug,
            'paymentAccount' => $paymentAccount->slug
        ]));
    }
}
