<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 11 Jul 2025 15:18:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\CustomerSalesChannel;

use App\Actions\RetinaAction;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class RetinaDeleteCustomerSalesChannel extends RetinaAction
{
    public function handle(CustomerSalesChannel $customerSalesChannel): ?bool
    {
        return DeleteCustomerSalesChannel::run($customerSalesChannel);
    }

    public function authorize(ActionRequest $request): bool
    {
        $customerSalesChannel = $request->route()->parameter('customerSalesChannel');
        if ($customerSalesChannel->customer_id == $request->user()->customer_id) {
            return true;
        }

        return false;
    }


    public function asController(CustomerSalesChannel $customerSalesChannel, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($customerSalesChannel);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }


}
