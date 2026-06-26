<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Sept 2025 11:56:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Accounting;

use App\Actions\Accounting\PaymentAccount\UpdatePaymentAccount;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentAccount;
use Illuminate\Console\Command;

class RepairPastpayAccountTerms
{
    use WithActionUpdate;

    protected function handle(PaymentAccount $paymentAccount, Command $command): void
    {
        UpdatePaymentAccount::run($paymentAccount, [
            'data' => [
                'charges' => [
                    'options' => [
                        ['days' => 30, 'charge' => '2.20'],
                        ['days' => 60, 'charge' => '4.25'],
                    ],
                ],
            ],
        ]);

        $command->info("Payment Account {$paymentAccount->slug} updated.");
    }

    public string $commandSignature = 'repair:pastpay_account_terms';

    public function asCommand(Command $command): void
    {
        PaymentAccount::where('type', PaymentAccountTypeEnum::PASTPAY)->chunk(100, function ($paymentAccounts) use ($command) {
            foreach ($paymentAccounts as $paymentAccount) {
                $this->handle($paymentAccount, $command);
            }
        });
    }

}
