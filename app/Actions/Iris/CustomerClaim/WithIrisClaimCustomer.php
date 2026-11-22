<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CustomerClaim;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;

trait WithIrisClaimCustomer
{
    private function claimCustomer(): Customer
    {
        $customerId = $this->signedInCustomerId();
        $customer   = $customerId ? Customer::where('shop_id', $this->website->shop_id)->find($customerId) : null;
        abort_unless($customer && GetCustomerClaimForm::enabledFor($customer->shop), 404);

        return $customer;
    }

    private function claimOrder(Customer $customer, int $orderId): Order
    {
        $order = Order::where('customer_id', $customer->id)->find($orderId);
        abort_unless($order, 404);

        return $order;
    }
}
