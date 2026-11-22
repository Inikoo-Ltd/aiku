<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\CustomerClaim;

use App\Actions\CRM\CustomerClaim\GetCustomerClaimForm;
use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;

class ShowIrisCustomerClaimForm extends IrisAction
{
    use WithIrisClaimCustomer;

    /**
     * @return array<string, mixed>
     */
    public function handle(Customer $customer, Order $order): array
    {
        $form = GetCustomerClaimForm::run($customer, $order);
        abort_unless($form, 404);

        return $form;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(int $orderId, ActionRequest $request): array
    {
        $this->initialisation($request);
        $customer = $this->claimCustomer();

        return $this->handle($customer, $this->claimOrder($customer, $orderId));
    }
}
