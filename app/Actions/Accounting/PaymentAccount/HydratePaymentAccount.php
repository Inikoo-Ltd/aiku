<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Feb 2023 11:34:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\PaymentAccount;

use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePAS;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Accounting\PaymentAccount;

class HydratePaymentAccount
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:payment_accounts {organisations?*} {--S|shop= shop slug} {--s|slug=} ';

    public function __construct()
    {
        $this->model = PaymentAccount::class;
    }

    public function handle(PaymentAccount $paymentAccount): void
    {
        PaymentAccountHydratePayments::run($paymentAccount);
        PaymentAccountHydrateCustomers::run($paymentAccount);
        PaymentAccountHydratePAS::run($paymentAccount);
    }


}
