<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\BackInStock\UI;

use App\Actions\CRM\BackInStockReminder\UI\IndexCustomerBackInStockReminders;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerBackInStockRemindersResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaEcomBackInStocks extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        return IndexCustomerBackInStockReminders::run($customer, $prefix);
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return IndexCustomerBackInStockReminders::make()->tableStructure($customer, $prefix);
    }

    private function getBasketTransactions(Customer $customer): array
    {
        if (!$customer->current_order_in_basket_id) {
            return [];
        }

        $order = Order::find($customer->current_order_in_basket_id);
        if (!$order) {
            return [];
        }

        // Get transactions the same way as ShowRetinaEcomBasket
        $transactions = $order->transactions()
            ->whereIn('model_type', ['Product', 'Service'])
            ->with(['asset.product'])
            ->get();

        $basketTransactions = [];
        foreach ($transactions as $transaction) {
            // Use product ID as key to match with favorites data (products.id)
            $productId = $transaction->asset?->product?->id;

            if ($productId) {
                $basketTransactions[$productId] = [
                    'id' => $transaction->id,
                    'quantity_ordered' => (int) $transaction->quantity_ordered,
                    'asset_id' => $transaction->asset_id,
                    'product_id' => $productId,
                ];
            }
        }

        return $basketTransactions;
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        return $this->handle($this->customer);
    }



    public function htmlResponse(LengthAwarePaginator $productFavorites, ActionRequest $request): Response
    {
        $basketTransactions = $this->getBasketTransactions($this->customer);

        return Inertia::render(
            'Ecom/Favourites',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Back In Stocks'),
                'pageHead' => [
                    'title'         => __('Back In Stocks'),
                    'icon'          => 'fal fa-heart',
                ],
                'data'     => CustomerBackInStockRemindersResource::collection($productFavorites),
                'basketTransactions' => $basketTransactions,

            ]
        )->table($this->tableStructure($this->customer));
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    // [
                    //     'type'   => 'simple',
                    //     'simple' => [
                    //         'route' => [
                    //             'name' => 'retina.dropshipping.favourites.index'
                    //         ],
                    //         'label'  => __('Favourites'),
                    //     ]
                    // ]
                ]
            );
    }




}
