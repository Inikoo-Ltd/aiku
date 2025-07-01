<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\CRM\Customer\UI\GetCustomerClientShowcase;
use App\Actions\RetinaAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\UI\CRM\CustomerClientTabsEnum;
use App\Enums\UI\CRM\CustomerTabsEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCustomerClient extends RetinaAction
{
    use WithActionButtons;

    public function handle(CustomerClient $customerClient): CustomerClient
    {
        return $customerClient;
    }


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }


    public function asController(
        CustomerSalesChannel $customerSalesChannel,
        CustomerClient $customerClient,
        ActionRequest $request
    ): CustomerClient {
        $this->initialisation($request)->withTab(CustomerTabsEnum::values());

        return $this->handle($customerClient);
    }


    public function htmlResponse(CustomerClient $customerClient, ActionRequest $request): Response
    {

        $actions = [];

        if ($customerClient->salesChannel->platform->type == PlatformTypeEnum::MANUAL) {
            $actions = [
                $this->getEditActionIcon($request, null),
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('Create Order'),
                    'route' => [
                        'name'       => 'retina.models.customer-client.order.store',
                        'parameters' => [
                            'customerClient' => $customerClient->id,
                        ],
                        'method'     => 'post'
                    ]
                ]
            ];
        }


        return Inertia::render(
            'Dropshipping/Client/CustomerClient',
            [
                'title'       => __('customer client'),
                'breadcrumbs' => $this->getBreadcrumbs($customerClient, $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'   => $customerClient->name,
                    'icon'    => [
                        'icon'  => ['fal', 'fa-user-friends'],
                        'title' => __('customer client')
                    ],
                    'actions' => $actions
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => CustomerClientTabsEnum::navigation()

                ],

                CustomerClientTabsEnum::SHOWCASE->value => $this->tab == CustomerClientTabsEnum::SHOWCASE->value ?
                    fn () => GetCustomerClientShowcase::run($customerClient)
                    : Inertia::lazy(fn () => GetCustomerClientShowcase::run($customerClient)),


            ]
        );
    }


    public function jsonResponse(CustomerClient $customerClient): CustomerClientResource
    {
        return new CustomerClientResource($customerClient);
    }


    public function getBreadcrumbs(CustomerClient $customerClient, $routeParameters): array
    {
        return array_merge(
            IndexRetinaCustomerClients::make()->getBreadcrumbs($customerClient->salesChannel),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'retina.dropshipping.customer_sales_channels.client.show',
                            'parameters' => [
                                'customerSalesChannel' => $routeParameters['customerSalesChannel'],
                                'customerClient'       => $customerClient->ulid
                            ]
                        ],
                        'label' => $customerClient->name,
                    ]
                ]
            ]
        );
    }


}
