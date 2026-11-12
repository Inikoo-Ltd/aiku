<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Iris\ShoppingAssistant;

use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * "Forget this" on the Range Advisor panel: clears what the assistant remembered about the
 * customer's shop. It works even with the assistant switched off, so a customer can always clear it.
 */
class ForgetIrisShoppingAssistantShopProfile extends IrisAction
{
    public function handle(Customer $customer): void
    {
        DB::table('customers')->where('id', $customer->id)->update(['shop_profile' => null]);
    }

    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);

        $customerId = $this->signedInCustomerId();
        abort_unless($customerId, 404);

        $this->handle(Customer::findOrFail($customerId));

        return response()->noContent();
    }
}
