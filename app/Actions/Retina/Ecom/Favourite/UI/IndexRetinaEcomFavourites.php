<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Favourite\UI;

use App\Actions\CRM\Favourite\UI\IndexRetinaCustomerFavourites;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Actions\Retina\Traits\HasBasketTransactions;
use App\Http\Resources\CRM\RetinaCustomerFavouritesResource;
use App\Models\CRM\Customer;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaEcomFavourites extends RetinaAction
{
    use HasBasketTransactions;

    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        return IndexRetinaCustomerFavourites::run($customer, $prefix);
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return IndexRetinaCustomerFavourites::make()->tableStructure($customer, $prefix);
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
                'breadcrumbs'               => $this->getBreadcrumbs(),
                'title'                     => __('Favourites'),
                'pageHead'                  => [
                    'title' => __('Favourites'),
                    'icon'  => 'fal fa-heart',
                ],
                'data'                      => RetinaCustomerFavouritesResource::collection($productFavorites),
                'basketTransactions'        => $basketTransactions,
                'attachToFavouriteRoute'    => [
                    'name' => 'retina.models.product.favourite'
                ],
                'detachToFavouriteRoute'    => [
                    'name' => 'retina.models.product.unfavourite'
                ],
                'attachBackInStockRoute'    => [
                    'name' => 'retina.models.remind_back_in_stock.store'
                ],
                'detachBackInStockRoute'    => [
                    'name' => 'retina.models.remind_back_in_stock.delete'
                ],
                'addToBasketRoute'          => [
                    'name'   => 'retina.models.product.add-to-basket',
                    'method' => 'post'
                ],
                'updateBasketQuantityRoute' => [
                    'name'   => 'retina.models.transaction.update',
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
