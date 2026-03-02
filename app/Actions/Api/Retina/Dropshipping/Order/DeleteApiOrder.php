<?php

/*
 * author Arya Permana - Kirin
 * created on 25-06-2025-15h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Order;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateOrders;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Masters\MasterShop\Hydrators\MasterShopHydrateOrders;
use App\Actions\RetinaApiAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrders;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrders;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteApiOrder extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order): JsonResponse
    {
        if ($order->customer_id != $this->customer->id || $order->shop_id != $this->shop->id) {
            return response()->json([
                'message' => "Unable to delete this order"
            ], 403);
        }

        if ($order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => "Order is in '{$order->state->value}' state and cannot be deleted.",
            ], 409);
        } 

        $client = $order->customerClient;
        $order->transactions()->delete();
        $order->delete();

        MasterShopHydrateOrders::dispatch($order->master_shop_id);
        ShopHydrateOrders::dispatch($this->shop);
        OrganisationHydrateOrders::dispatch($this->organisation);
        GroupHydrateOrders::dispatch($this->group);
        CustomerHydrateOrders::dispatch($this->customer->id);
        CustomerClientHydrateOrders::dispatch($client);
        CustomerSalesChannelsHydrateOrders::dispatch($this->customerSalesChannel);

        return response()->json([
            'message' => 'Order deleted successfully',
            'order_id' => $order->id
        ]);
    }

    public function asController(Order $order, ActionRequest $request): JsonResponse
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($order);
    }
}
