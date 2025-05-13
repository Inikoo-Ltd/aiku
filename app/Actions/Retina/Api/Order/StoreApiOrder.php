<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Order;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateOrders;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateOrders;
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Sales\OrderResource;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreApiOrder
{
    use AsAction;
    use WithAttributes;

    public function handle(Customer $customer): Order
    {
        $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();
        $order = StoreOrder::make()->action($customer, [
            'platform_id' => $platform->id
        ]);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Customer $customer): Order
    {
        return $this->handle($customer);
    }

    public function jsonResponse(Order $order)
    {
        return OrderResource::make($order)
            ->additional([
                'meta' => [
                    'message' => __('Order created successfully'),
                ],
            ]);
    }
}
