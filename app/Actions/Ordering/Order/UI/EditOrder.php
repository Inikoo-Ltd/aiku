<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Actions\Retina\Ecom\Basket\UI\IsOrder;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Enums\UI\Ordering\OrderTabsEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrder extends OrgAction
{
    use IsOrder;
    use WithOrderingEditAuthorisation;

    private Shop|Customer|CustomerClient|Purge|CustomerSalesChannel $parent;
    private CustomerSalesChannel $customerSalesChannel;

    public function handle(Order $order): Order
    {
        return $order;
    }

    public function inOrganisation(Organisation $organisation, Order $order, ActionRequest $request): Order
    {
        $this->initialisation($organisation, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }

    public function asController(Organisation $organisation, Shop $shop, Order $order, ActionRequest $request): Order
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)->withTab(OrderTabsEnum::values());

        return $this->handle($order);
    }


    public function htmlResponse(Order $order, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $order,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title'    => $order->slug,
                    'actions'  => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ]
                    ]
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('id'),
                            'fields' => [
                                'reference' => [
                                    'type'  => 'input',
                                    'label' => __('Reference'),
                                    'value' => $order->reference
                                ],
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.order.update',
                            'parameters' => [
                                'order'     => $order->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Order $order, string $routeName, array $routeParameters): array
    {
        return ShowOrder::make()->getBreadcrumbs(
            order: $order,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Edit').')'
        );

    }
}
