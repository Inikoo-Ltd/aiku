<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class CancelOrder extends OrgAction
{
    use WithOrderingEditAuthorisation;
    use WithActionUpdate;

    public function handle(Order $order): Order
    {
        $order = $this->update($order, [
            'state' => OrderStateEnum::CANCELLED
        ]);

        OrganisationHydrateOrders::dispatch($order->organisation);
        ShopHydrateOrders::dispatch($order->shop);

        return $order;
    }

    public function action(Order $order): Order
    {
        return $this->handle($order);
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($order);
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return new OrderResource($order);
    }
}
