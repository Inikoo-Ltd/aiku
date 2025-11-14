<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Nov 2025 18:54:49 Malaysia Time, Plane KL - Bali
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectOrder extends OrgAction
{
    public function handle(Order $order): ?RedirectResponse
    {

        $url = route('grp.org.shops.show.ordering.orders.show', [
            $order->organisation->slug,
            $order->shop->slug,
            $order->slug
        ]);
        return Redirect::to($url);
    }



    public function asController(Order $order, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($order->organisation, $request);

        return $this->handle($order);
    }

}
