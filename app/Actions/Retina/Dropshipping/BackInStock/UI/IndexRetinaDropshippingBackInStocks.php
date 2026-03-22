<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 6 Jan 2026 10:02:57 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Retina\Dropshipping\BackInStock\UI;

use App\Actions\Comms\BackInStockReminder\UI\IndexCustomerBackInStockReminders;
use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\CRM\CustomerBackInStockRemindersResource;
use App\Models\CRM\Customer;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexRetinaDropshippingBackInStocks extends RetinaAction
{
    public function handle(Customer $customer, $prefix = null): LengthAwarePaginator
    {
        return IndexCustomerBackInStockReminders::run($customer, $prefix);
    }

    public function tableStructure(Customer $customer, $prefix = null): Closure
    {
        return IndexCustomerBackInStockReminders::make()->tableStructure($customer, $prefix);
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer);
    }


    public function htmlResponse(LengthAwarePaginator $productFavorites, ActionRequest $request): Response
    {
        return Inertia::render(
            'Dropshipping/RetinaDropshippingBackInStocks',
            [
                'breadcrumbs'               => $this->getBreadcrumbs(),
                'title'                     => __('Back In Stocks'),
                'pageHead'                  => [
                    'title' => __('Back In Stocks'),
                    'icon'  => 'fal fa-heart',
                ],
                'data'                      => CustomerBackInStockRemindersResource::collection($productFavorites),
                'basketTransactions'        => [],
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
                    'name' => 'retina.models.product.add-to-basket'
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
