<?php

/*
 * author Arya Permana - Kirin
 * created on 14-04-2025-14h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Dropshipping\Orders;

use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\Ordering\Order\StoreOrder;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\HistoricAsset;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaPlatformOrder extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, Platform $platform, array $modelData): Order
    {
        $order = DB::transaction(function () use ($customer, $platform, $modelData) {
            $order = StoreOrder::make()->action($customer, [
                'platform_id' => $platform->id
            ]);

            foreach ($modelData['products'] as $product) {
                $historicAsset = HistoricAsset::find($product['id']);
                StoreTransaction::make()->action($order, $historicAsset, [
                    'quantity_ordered' => $product['quantity']
                ]);
            }
            return $order;
        });

        $customerSalesChannel = $customer->customerSalesChannels()
            ->where('platform_id', $platform->id)
            ->first();

        CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);

        return $order;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
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
        $this->initialisation($request);

        return $this->handle($customer, $platform, $this->validatedData);
    }
}
