<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(CustomerClient $customerClient): Order
    {
        $order = StoreOrder::make()->action($customerClient, [
            'platform_id' => $customerClient->platform_id,
            'customer_sales_channel_id' => $customerClient->customer_sales_channel_id
        ]);

        CustomerSalesChannelsHydrateOrders::dispatch($customerClient->salesChannel);

        CustomerClientHydrateOrders::dispatch($customerClient);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function htmlResponse(Order $order)
    {
        return Redirect::route('retina.dropshipping.customer_sales_channels.basket.show', [
            $order->customerSalesChannel->slug,
            $order->slug
        ]);
    }

    public function inCustomerClient(CustomerClient $customerClient, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($customerClient->platform, $request);

        return $this->handle($customerClient);
    }

    public function inDashboard(CustomerClient $customerClient, ActionRequest $request): Order
    {
        $platform = $customerClient->platform;

        if ($platform) {
            $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        }

        $this->initialisationFromPlatform($platform, $request);

        return $this->handle($customerClient);
    }
}
