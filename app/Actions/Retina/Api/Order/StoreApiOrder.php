<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-09h-57m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Order;

use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Http\Resources\Api\OrderResource;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreApiOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(Customer $customer): Order
    {
        $platform = Platform::where('type', PlatformTypeEnum::MANUAL)->first();

        return StoreOrder::make()->action($customer, [
            'platform_id' => $platform->id
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): Order
    {
        return $this->handle($request->user());
    }

    public function jsonResponse(Order $order): OrderResource
    {
        return OrderResource::make($order)
            ->additional([
                'message' => __('Order created successfully'),
            ]);
    }
}
