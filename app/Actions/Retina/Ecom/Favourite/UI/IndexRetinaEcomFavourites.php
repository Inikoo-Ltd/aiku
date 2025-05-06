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


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        return $this->handle($this->customer);
    }



    public function htmlResponse(LengthAwarePaginator $productFavorites, ActionRequest $request): Response
    {
        return Inertia::render(
            'Ecom/Basket',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('Favourites'),
                'pageHead' => [
                    'title'         => __('Favourites'),
                    'icon'          => 'fal fa-heart',

                ],
                'data'     => CustomerFavouritesResource::collection($productFavorites),

            ]
        )->table($this->tableStructure($this->customer));
    }

    public function getBreadcrumbs(): array
    {
        return
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name' => 'retina.dropshipping.favourites.index'
                            ],
                            'label'  => __('Favourites'),
                        ]
                    ]
                ]
            );
    }




}
