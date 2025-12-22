<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Favourite\UI;

use App\Actions\CRM\Favourite\UI\IndexCustomerFavourites;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerFavouritesResource;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaEcomFavourites extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        return IndexCustomerFavourites::run($customer, $prefix);
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return IndexCustomerFavourites::make()->tableStructure($customer, $prefix);
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
        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            // Use product ID as a key to match with favorites data (products.id)
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
                'title'       => __('Favourites'),
                'pageHead' => [
                    'title'         => __('Favourites'),
                    'icon'          => 'fal fa-heart',
                ],
                'data'     => CustomerFavouritesResource::collection($productFavorites),
                'basketTransactions' => $basketTransactions,
                'attachToFavouriteRoute' => [
                    'name' => 'retina.models.product.favourite'
                ],
                'dettachToFavouriteRoute' => [
                    'name' => 'retina.models.product.unfavourite'
                ],
                'attachBackInStockRoute' => [
                    'name' => 'retina.models.remind_back_in_stock.store'
                ],
                'detachBackInStockRoute' => [
                    'name' => 'retina.models.remind_back_in_stock.delete'
                ],
                'addToBasketRoute' => [
                    'name' => 'retina.models.product.add-to-basket',
                    'method' => 'post'
                ],
                'updateBasketQuantityRoute' => [
                    'name' => 'retina.models.transaction.update',
                    'method' => 'patch'
                ]
            ]
        )->table($this->tableStructure($this->customer));
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [

                ]
            );
    }




}
