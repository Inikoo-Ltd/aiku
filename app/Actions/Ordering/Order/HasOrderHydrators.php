<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 17:35:41 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrdersDispatchedToday;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateCreating;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateFinalised;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandling;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateHandlingBlocked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateInWarehouse;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStatePacked;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrderStateSubmitted;
use App\Actions\Catalogue\ShopPlatformStats\ShopPlatformStatsHydrateOrders;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Dropshipping\Platform\Hydrators\PlatformHydrateOrders;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateCreating;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandling;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStatePacked;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateSubmitted;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateCreating;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandling;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateHandlingBlocked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateInWarehouse;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStatePacked;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrderStateSubmitted;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;

trait HasOrderHydrators
{
    public function orderHydrators(Order $order): void
    {
        GroupHydrateOrders::dispatch($order->shop->group)->delay($this->hydratorsDelay);

        OrganisationHydrateOrders::dispatch($order->shop->organisation)->delay($this->hydratorsDelay);

        ShopHydrateOrders::dispatch($order->shop)->delay($this->hydratorsDelay);


        if ($order->master_shop_id) {
            MasterShopHydrateOrders::dispatch($order->master_shop_id)->delay($this->hydratorsDelay);
        }
        if ($order->customer_id) {
            CustomerHydrateOrders::dispatch($order->customer_id)->delay($this->hydratorsDelay);
        }

        if ($order->platform_id) {
            PlatformHydrateOrders::dispatch($order->platform)->delay($this->hydratorsDelay);
            ShopPlatformStatsHydrateOrders::dispatch($order->shop, $order->platform)->delay($this->hydratorsDelay);
        }

        if ($order->customerSalesChannel) {
            CustomerSalesChannelsHydrateOrders::dispatch($order->customerSalesChannel);
        }
    }

    public function orderHandlingHydrators(Order $order, OrderStateEnum $orderState): void
    {
        if ($orderState == OrderStateEnum::CREATING) {
            GroupHydrateOrderStateCreating::dispatch($order->group_id);
            OrganisationHydrateOrderStateCreating::dispatch($order->organisation_id);
            ShopHydrateOrderStateCreating::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::SUBMITTED) {
            GroupHydrateOrderStateSubmitted::dispatch($order->group_id);
            OrganisationHydrateOrderStateSubmitted::dispatch($order->organisation_id);
            ShopHydrateOrderStateSubmitted::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::IN_WAREHOUSE) {
            GroupHydrateOrderStateInWarehouse::dispatch($order->group_id);
            OrganisationHydrateOrderStateInWarehouse::dispatch($order->organisation_id);
            ShopHydrateOrderStateInWarehouse::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::HANDLING) {
            GroupHydrateOrderStateHandling::dispatch($order->group_id);
            OrganisationHydrateOrderStateHandling::dispatch($order->organisation_id);
            ShopHydrateOrderStateHandling::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::HANDLING_BLOCKED) {
            GroupHydrateOrderStateHandlingBlocked::dispatch($order->group_id);
            OrganisationHydrateOrderStateHandlingBlocked::dispatch($order->organisation_id);
            ShopHydrateOrderStateHandlingBlocked::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::PACKED) {
            GroupHydrateOrderStatePacked::dispatch($order->group_id);
            OrganisationHydrateOrderStatePacked::dispatch($order->organisation_id);
            ShopHydrateOrderStatePacked::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::FINALISED) {
            GroupHydrateOrderStateFinalised::dispatch($order->group_id);
            OrganisationHydrateOrderStateFinalised::dispatch($order->organisation_id);
            ShopHydrateOrderStateFinalised::dispatch($order->shop_id);
        } elseif ($orderState == OrderStateEnum::DISPATCHED) {
            GroupHydrateOrdersDispatchedToday::dispatch($order->group_id);
            OrganisationHydrateOrdersDispatchedToday::dispatch($order->organisation_id);
            ShopHydrateOrdersDispatchedToday::dispatch($order->shop_id);
        }
    }

}
