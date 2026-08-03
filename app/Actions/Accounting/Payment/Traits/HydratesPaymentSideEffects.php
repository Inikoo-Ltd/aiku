<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jun 2026 22:08:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Payment\Traits;

use App\Actions\Accounting\OrgPaymentServiceProvider\Hydrators\OrgPaymentServiceProviderHydratePayments;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydrateCustomers;
use App\Actions\Accounting\PaymentAccount\Hydrators\PaymentAccountHydratePayments;
use App\Actions\Accounting\PaymentServiceProvider\Hydrators\PaymentServiceProviderHydratePayments;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydratePayments;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydratePayments;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydratePayments;
use App\Models\Accounting\Payment;

trait HydratesPaymentSideEffects
{
    public function hydratePaymentSideEffects(Payment $payment): void
    {
        GroupHydratePayments::dispatch($payment->group_id)->delay(300);
        OrganisationHydratePayments::dispatch($payment->organisation_id)->delay(5);
        PaymentServiceProviderHydratePayments::dispatch($payment->paymentAccount->paymentServiceProvider)->delay(5);
        PaymentAccountHydratePayments::dispatch($payment->paymentAccount)->delay(5);
        PaymentAccountHydrateCustomers::dispatch($payment->paymentAccount)->delay(5);
        ShopHydratePayments::dispatch($payment->shop_id)->delay(5);
        OrgPaymentServiceProviderHydratePayments::dispatch($payment->orgPaymentServiceProvider)->delay(5);
    }
}
