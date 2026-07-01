<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-16h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop;

use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePAS;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePaymentAccountShop extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    /**
     * @var \App\Models\Accounting\PaymentAccountShop
     */
    private PaymentAccountShop $paymentAccountShop;

    public function handle(PaymentAccountShop $paymentAccountShop, array $modelData): PaymentAccountShop
    {
        if ($paymentAccountShop->type == PaymentAccountTypeEnum::PASTPAY) {
            $pastpayData = $paymentAccount->data ?? [];
            if (Arr::exists($modelData, 'pastpay_charges')) {
                $pastpayData['charges'] = [
                    'options' => Arr::pull($modelData, 'pastpay_charges')
                ];
            }

            data_set($modelData, 'data', $pastpayData);
        }

        $paymentAccountShop = $this->update($paymentAccountShop, $modelData);



        if ($paymentAccountShop->wasChanged('state')) {
            PaymentAccountHydratePAS::dispatch($paymentAccountShop->paymentAccount)->delay($this->hydratorsDelay);
        }

        return $paymentAccountShop;
    }

    public function rules(): array
    {
        $rules = [
            'state'                     => [
                'sometimes',
                Rule::enum(PaymentAccountShopStateEnum::class)
            ],
            'show_in_checkout'          => ['sometimes', 'boolean'],
            'checkout_display_position' => ['sometimes', 'numeric'],
            'data'                      => ['sometimes', 'array'],
            'invoice_footer'            => ['sometimes', 'string', 'max:10000']
        ];

        if ($this->paymentAccountShop->type == PaymentAccountTypeEnum::PASTPAY) {
            $rules['pastpay_charges']    = ['sometimes'];
        }

        if (!$this->strict) {
            $rules                      = $this->noStrictUpdateRules($rules);
            $rules['currency_id']       = ['sometimes', 'required', Rule::Exists('currencies', 'id')];
            $rules['activated_at']      = ['sometimes', 'date'];
            $rules['last_activated_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function asController(PaymentAccountShop $paymentAccountShop, ActionRequest $request): PaymentAccountShop
    {
        $this->paymentAccountShop = $paymentAccountShop;
        $this->initialisation($paymentAccountShop->paymentAccount->organisation, $request);

        return $this->handle($paymentAccountShop, $this->validateAttributes());
    }

    public function action(PaymentAccountShop $paymentAccountShop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): PaymentAccountShop
    {
        $this->strict = $strict;
        if (!$audit) {
            PaymentAccountShop::disableAuditing();
        }
        $this->hydratorsDelay = $hydratorsDelay;
        $this->paymentAccountShop = $paymentAccountShop;
        $this->initialisation($paymentAccountShop->paymentAccount->organisation, $modelData);

        return $this->handle($paymentAccountShop, $this->validateAttributes());
    }

}
