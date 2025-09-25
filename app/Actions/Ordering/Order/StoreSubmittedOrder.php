<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreSubmittedOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): Order
    {
        $order = StoreOrder::make()->action($customer, []);
        SubmitOrder::make()->action($order);
        $order->refresh();

        return $order;
    }

        public function htmlResponse(Order $order, ActionRequest $request): RedirectResponse
    {
        $routeName = $request->route()->getName();

        return match ($routeName) {
            'grp.models.customer.submitted_order.store' => Redirect::route('grp.org.shops.show.crm.customers.show.orders.show', [
                $order->organisation->slug,
                $order->shop->slug,
                $order->customer->slug,
                $order->slug
            ])
        };
    }
    /**
     * @throws \Throwable
     */
    public function asController(Customer $customer, ActionRequest $request): Order
    {
        $this->initialisationFromShop($customer->shop, $request);

        return $this->handle($customer);
    }
}
