<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 28 Sept 2025 12:22:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccountShop;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Console\Command;
use Lorisleiva\Actions\ActionRequest;

class ActivatePaymentAccountShop extends OrgAction
{
    use WithNoStrictRules;

    public function handle(PaymentAccountShop $paymentAccountShop): PaymentAccountShop
    {
        $modelData = [
            'state'             => PaymentAccountShopStateEnum::ACTIVE,
            'activated_at'      => now(),
            'last_activated_at' => now(),
        ];

        if ($paymentAccountShop->activated_at) {
            data_forget($modelData, 'activated_at');
        }

        UpdatePaymentAccountShop::run(
            $paymentAccountShop,
            $modelData
        );

        return $paymentAccountShop;
    }


    public function asController(PaymentAccountShop $paymentAccountShop, ActionRequest $request): PaymentAccountShop
    {
        $this->initialisationFromShop($paymentAccountShop->shop, $request);

        return $this->handle($paymentAccountShop);
    }


    public function getCommandSignature(): string
    {
        return 'payment_account_shop:activate {payment_account_shop}';
    }

    public function asCommand(Command $command): int
    {
        $paymentAccountShop = PaymentAccountShop::findOrFail($command->argument('payment_account_shop'));
        $this->handle($paymentAccountShop);

        return 0;
    }

}
