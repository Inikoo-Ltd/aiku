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
use App\Actions\Dropshipping\CustomerHasPlatforms\Hydrators\CustomerHasPlatformsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\HistoricAsset;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(CustomerClient|Customer $parent, Platform $platform): Order
    {
        $order = StoreOrder::make()->action($parent, [
            'platform_id' => $platform->id
        ]);

        $customerHasPlatform = $this->customer->customerHasPlatforms()
            ->where('platform_id', $platform->id)
            ->first();

        CustomerHasPlatformsHydrateOrders::dispatch($customerHasPlatform);
        
        if($parent instanceof CustomerClient) {
            CustomerClientHydrateOrders::dispatch($parent);
        } elseif ($parent instanceof Customer) {
            CustomerHydrateOrders::dispatch($parent);
        }

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function htmlResponse(Order $order)
    {
        return Redirect::route('retina.dropshipping.platforms.orders.show', [
            $order->platform->slug,
            $order->slug
        ]);
    }

    public function asController(Customer $customer, Platform $platform, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($platform, $request);

        return $this->handle($customer, $platform);
    }

    public function inCustomerClient(CustomerClient $customerClient, Platform $platform, ActionRequest $request): Order
    {
        $this->initialisationFromPlatform($platform, $request);

        return $this->handle($customerClient, $platform);
    }
}
