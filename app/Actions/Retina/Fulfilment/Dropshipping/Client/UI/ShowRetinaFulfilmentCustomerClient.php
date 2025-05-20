<?php
/*
 * author Arya Permana - Kirin
 * created on 20-05-2025-11h-37m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Dropshipping\Client\UI;

use App\Actions\CRM\Customer\UI\GetCustomerClientShowcase;
use App\Actions\Retina\Fulfilment\Client\UI\IndexRetinaFulfilmentPlatformCustomerClients;
use App\Actions\Retina\Fulfilment\Dropshipping\Client\UI\IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Enums\UI\CRM\CustomerClientTabsEnum;
use App\Enums\UI\CRM\CustomerTabsEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Dropshipping\CustomerSalesChannel;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaFulfilmentCustomerClient extends RetinaAction
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

        return Inertia::render(
            'Dropshipping/Client/CustomerClient',
            [
                'title'       => __('customer client'),
                'breadcrumbs' => $this->getBreadcrumbs($customerClient, 
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title'     => $customerClient->name,
                    'model'     => __($customerClient->customer->name),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('customer client')
                    ],
                    'actions'    => [
                        $this->getEditActionIcon($request, ''),
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create Order'),
                            'route' => [
                                'name'       => 'retina.models.customer-client.fulfilment_order.store',
                                'parameters' => [
                                    'customerClient' => $customerClient->id,
                                ],
                                'method'     => 'post'
                            ]
                        ]
                    ]
                ],
                'tabs'          => [
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

    public function getBreadcrumbs(CustomerClient $customerClient, $routeName, $routeParameters): array
    {
        return array_merge(
                IndexRetinaFulfilmentCustomerClientsInCustomerSalesChannel::make()->getBreadcrumbs($routeName, $routeParameters),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.fulfilment.dropshipping.customer_sales_channels.client.show',
                                'parameters' => [
                                    'customerSalesChannel' => $routeParameters['customerSalesChannel'],
                                    'customerClient' => $customerClient->ulid
                                ]
                            ],
                            'label' => $customerClient->name,
                        ]
                    ]
                ]
            );
    }


}
