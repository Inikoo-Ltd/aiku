<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 17:35:41 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Models\Ordering\Order;

trait HasOrderHydrators
{
    public function orderHydrators(Order $order): void
    {
        GroupHydrateOrders::dispatch($order->shop->group)->delay($this->hydratorsDelay);
        OrganisationHydrateOrders::dispatch($order->shop->organisation)->delay($this->hydratorsDelay);
        ShopHydrateOrders::dispatch($order->shop)->delay($this->hydratorsDelay);
        if ($order->customer_id) {
            CustomerHydrateOrders::dispatch($order->customer)->delay($this->hydratorsDelay);
        }

        if ($order->platform_id) {
            PlatformHydrateOrders::dispatch($order->platform)->delay($this->hydratorsDelay);
        }
    }
}
