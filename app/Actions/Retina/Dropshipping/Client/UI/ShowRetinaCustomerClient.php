<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 16 Oct 2024 10:47:26 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Client\UI;

use App\Actions\CRM\Customer\UI\GetCustomerClientShowcase;
use App\Actions\RetinaAction;
use App\Enums\UI\CRM\CustomerClientTabsEnum;
use App\Enums\UI\CRM\CustomerTabsEnum;
use App\Http\Resources\CRM\CustomerClientResource;
use App\Models\Dropshipping\CustomerClient;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowRetinaCustomerClient extends RetinaAction
{
    public function handle(CustomerClient $customerClient): CustomerClient
    {
        return $customerClient;
    }


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }


    public function asController(
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
                'breadcrumbs' => $this->getBreadcrumbs($customerClient),
                'pageHead' => [
                    'title'     => $customerClient->name,
                    'model'     => __($customerClient->customer->name),
                    'icon'      => [
                        'icon'  => ['fal', 'fa-folder'],
                        'title' => __('customer client')
                    ],
                    'actions'    => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('Create Order'),
                            'route' => [
                                'name'       => 'retina.models.customer-client.order.store',
                                'parameters' => [
                                    'customerClient' => $customerClient->id,
                                    'platform' => $customerClient->platform->id
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

    public function getBreadcrumbs(CustomerClient $customerClient): array
    {
        return
            array_merge(
                IndexRetinaCustomerClients::make()->getBreadcrumbs(),
                [
                    [
                        'type'   => 'simple',
                        'simple' => [
                            'route' => [
                                'name'       => 'retina.dropshipping.client.show',
                                'parameters' => [$customerClient->ulid]
                            ],
                            'label' => $customerClient->name,
                        ]
                    ]
                ]
            );
    }


}
