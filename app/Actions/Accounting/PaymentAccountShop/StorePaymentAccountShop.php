<?php

/*
 * author Arya Permana - Kirin
 * created on 17-02-2025-15h-36m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\PaymentAccountShop;

use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePAS;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePaymentAccountShop extends OrgAction
{
    use WithNoStrictRules;

    public function handle(PaymentAccount $paymentAccount, Shop $shop, array $modelData): PaymentAccountShop
    {
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'type', $paymentAccount->type);

        data_set($modelData, 'currency_id', $shop->currency_id, overwrite: false);
        data_set($modelData, 'state', PaymentAccountShopStateEnum::IN_PROCESS, overwrite: false);


        if (Arr::has($modelData, 'state') && $modelData['state'] == PaymentAccountShopStateEnum::ACTIVE) {
            data_set($modelData, 'activated_at', now(), overwrite: false);
            data_set($modelData, 'last_activated_at', now(), overwrite: false);
        }

        /** @var PaymentAccountShop $paymentAccountShop */
        $paymentAccountShop = $paymentAccount->paymentAccountShops()->create($modelData);
        PaymentAccountHydratePAS::dispatch($paymentAccount)->delay($this->hydratorsDelay);

        return $paymentAccountShop;
    }

    public function rules(): array
    {
        $rules = [
            'state'                     => [
                'sometimes',
                'required',
                Rule::enum(PaymentAccountShopStateEnum::class)
            ],
            'currency_id'               => [
                'sometimes',
                'required',
                Rule::Exists('currencies', 'id')
            ],
            'show_in_checkout'          => ['sometimes', 'boolean'],
            'checkout_display_position' => ['sometimes', 'numeric'],
            'data'                      => ['sometimes', 'array']
        ];

        if (!$this->strict) {
            $rules                      = $this->noStrictStoreRules($rules);
            $rules['activated_at']      = ['sometimes', 'date'];
            $rules['last_activated_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function prepareForValidation(): void
    {
        if ($this->has('checkout_display_position')) {
            $checkoutDisplayPosition = $this->shop->paymentAccountShops()->max('checkout_display_position') + 10;
            $this->set('checkout_display_position', $checkoutDisplayPosition);
        }
    }

    public function asController(PaymentAccount $paymentAccount, Shop $shop, ActionRequest $request): PaymentAccountShop
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($paymentAccount, $shop, $this->validateAttributes());
    }

    public function action(PaymentAccount $paymentAccount, Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): PaymentAccountShop
    {
        if (!$audit) {
            PaymentAccountShop::disableAuditing();
        }
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($paymentAccount, $shop, $this->validateAttributes());
    }

    public function getCommandSignature(): string
    {
        return 'payment_account_shop:store {shop} {payment_account}';
    }

    public function asCommand(Command $command): int
    {
        $shop           = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $paymentAccount = PaymentAccount::where('slug', $command->argument('payment_account'))->firstOrFail();
        $this->handle($paymentAccount, $shop, []);

        return 0;
    }

}
