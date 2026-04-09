<?php

/*
 * author Arya Permana - Kirin
 * created on 14-02-2025-13h-56m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Bundle\UI;

use App\Actions\RetinaAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RedirectIrisToRetinaBundle extends RetinaAction
{
    use AsAction;

    public function asController(ActionRequest $request): RedirectResponse|Redirector
    {
        $this->initialisation($request);
        $customerSalesChannel = $this->customer->customerSalesChannels->first();

        return redirect("/app/dropshipping/channels/$customerSalesChannel->slug/my-products?tab=bundles");
    }
}
