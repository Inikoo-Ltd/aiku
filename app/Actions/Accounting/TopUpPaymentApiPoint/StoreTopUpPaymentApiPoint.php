<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 May 2025 18:11:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUpPaymentApiPoint;

use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\CRM\Customer;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class StoreTopUpPaymentApiPoint
{
    use AsObject;

    public function handle(Customer $customer, PaymentAccountShop $paymentAccountShop): OrderPaymentApiPoint
    {
        data_set($modelData, 'payment_account_shop_id', $paymentAccountShop->id);
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'ulid', Str::ulid());

        return $customer->topUpPaymentApiPoint()->create($modelData);

    }
}
