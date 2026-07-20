<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-16h-03m
 * GitHub: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop;

use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePAS;
use App\Actions\Billables\Charge\StoreCharge;
use App\Actions\Billables\Charge\UpdateCharge;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Billables\Charge;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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
        if (Arr::exists($modelData, 'is_active')) {
            data_set(
                $modelData,
                'state',
                Arr::pull($modelData, 'is_active')
                    ? PaymentAccountShopStateEnum::ACTIVE
                    : PaymentAccountShopStateEnum::INACTIVE
            );
        }

        if ($paymentAccountShop->type == PaymentAccountTypeEnum::PASTPAY) {
            $pastpayData = $paymentAccountShop->data ?? [];
            if (Arr::exists($modelData, 'pastpay_charges')) {
                $options                = $this->normalisePastpayChargeOptions(Arr::pull($modelData, 'pastpay_charges') ?? []);
                $pastpayData['charges'] = [
                    'options' => $options
                ];
                $this->upsertPastpayCharges($paymentAccountShop, $options);
            }

            data_set($modelData, 'data', $pastpayData);

            if ($taxNumber = Arr::pull($modelData, 'pastpay_tax_number')) {
                $paymentAccount = $paymentAccountShop->paymentAccount;
                $paymentAccount->update([
                    'data' => array_merge($paymentAccount->data ?? [], ['tax_number' => $taxNumber])
                ]);
            }
        }

        $paymentAccountShop = $this->update($paymentAccountShop, $modelData);



        if ($paymentAccountShop->wasChanged('state')) {
            PaymentAccountHydratePAS::dispatch($paymentAccountShop->paymentAccount)->delay($this->hydratorsDelay);
        }

        return $paymentAccountShop;
    }

    private function normalisePastpayChargeOptions(array $options): array
    {
        return array_values(array_filter(array_map(function ($option) {
            $days   = (int) Arr::get($option, 'days');
            $charge = (float) str_replace(',', '.', trim((string) Arr::get($option, 'charge')));

            if (!$days || $charge <= 0) {
                return null;
            }

            return [
                'days'   => $days,
                'charge' => (string) $charge,
            ];
        }, $options)));
    }

    private function upsertPastpayCharges(PaymentAccountShop $paymentAccountShop, array $options): void
    {
        $shop = $paymentAccountShop->shop;

        foreach ($options as $option) {
            $days = (int) Arr::get($option, 'days');
            if (!$days) {
                continue;
            }

            /** @var Charge $charge */
            $charge = $shop->charges()->where('code', 'like', "Pastpay-$days-%")->first();

            if ($charge) {
                if ($charge->state != ChargeStateEnum::ACTIVE) {
                    UpdateCharge::make()->action($charge, ['state' => ChargeStateEnum::ACTIVE]);
                }
            } else {
                StoreCharge::make()->action($shop, [
                    'code'        => "Pastpay-$days-".$shop->code,
                    'name'        => "Pastpay charge ($days days)",
                    'description' => "Pastpay charge ($days days)",
                    'state'       => ChargeStateEnum::ACTIVE,
                    'trigger'     => ChargeTriggerEnum::PAYMENT_ACCOUNT,
                    'type'        => ChargeTypeEnum::PAYMENT,
                ]);
            }
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->paymentAccountShop->type != PaymentAccountTypeEnum::PASTPAY) {
            return;
        }

        $activating = $this->get('is_active') === true
            || PaymentAccountShopStateEnum::tryFrom((string) $this->get('state')) == PaymentAccountShopStateEnum::ACTIVE;

        if (!$activating) {
            return;
        }

        $taxNumber = $this->get('pastpay_tax_number')
            ?? Arr::get($this->paymentAccountShop->paymentAccount->data, 'tax_number');
        $charges = $this->get('pastpay_charges')
            ?? Arr::get($this->paymentAccountShop->data, 'charges.options', []);
        $footer = $this->get('invoice_footer')
            ?? $this->paymentAccountShop->invoice_footer;

        if (blank($taxNumber)) {
            $validator->errors()->add('is_active', __('Set the creditor tax number before activating PastPay.'));
        }
        if (empty($charges)) {
            $validator->errors()->add('is_active', __('Add at least one credit term before activating PastPay.'));
        }
        if (blank(trim(strip_tags((string) $footer)))) {
            $validator->errors()->add('is_active', __('Set the invoice footer before activating PastPay.'));
        }
    }

    public function rules(): array
    {
        $rules = [
            'state'                     => [
                'sometimes',
                Rule::enum(PaymentAccountShopStateEnum::class)
            ],
            'is_active'                 => ['sometimes', 'boolean'],
            'show_in_checkout'          => ['sometimes', 'boolean'],
            'checkout_display_position' => ['sometimes', 'numeric'],
            'data'                      => ['sometimes', 'array'],
            'invoice_footer'            => ['sometimes', 'string', 'max:10000']
        ];

        if ($this->paymentAccountShop->type == PaymentAccountTypeEnum::PASTPAY) {
            $rules['pastpay_charges']    = ['sometimes'];
            $rules['pastpay_tax_number'] = ['sometimes', 'nullable', 'string', 'max:64'];
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
