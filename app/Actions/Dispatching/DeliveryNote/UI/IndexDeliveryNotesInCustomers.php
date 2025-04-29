<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Mar 2025 22:30:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\CRM\Customer\UI\ShowCustomerClient;
use App\Actions\CRM\Customer\UI\WithCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\CRM\CustomerHasPlatform;
use App\Models\Dropshipping\CustomerClient;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexDeliveryNotesInCustomers extends OrgAction
{
    use WithCustomerSubNavigation;
    use WithCRMAuthorisation;

    private Customer|CustomerClient $parent;
    private CustomerHasPlatform $customerHasPlatform;

    public function handle(Customer|CustomerClient $parent, $prefix = null): LengthAwarePaginator
    {
        return IndexDeliveryNotes::run($parent, $prefix);
    }


    public function tableStructure(Customer|CustomerClient $parent, $prefix = null): Closure
    {
        return IndexDeliveryNotes::make()->tableStructure($parent, $prefix);
    }


    public function htmlResponse(LengthAwarePaginator $deliveryNotes, ActionRequest $request): Response
    {
        if ($this->parent instanceof CustomerClient) {
            $subNavigation = $this->getCustomerClientSubNavigation($this->parent, $this->customerHasPlatform);
        } elseif ($this->parent instanceof Customer && $this->parent->shop->type == ShopTypeEnum::B2B) {
            $subNavigation = $this->getCustomerSubNavigation($this->parent, $request);
        } elseif ($this->parent instanceof Customer && $this->parent->shop->type == ShopTypeEnum::DROPSHIPPING) {
            $subNavigation = $this->getCustomerDropshippingSubNavigation($this->parent, $request);
        }


        $title      = __('Delivery notes');
        $model      = '';
        $icon       = [
            'icon'  => ['fal', 'fa-truck'],
            'title' => __('Delivery notes')
        ];

        $actions    = null;


        $iconRight  = $icon;
        $afterTitle = [
            'label' => $title
        ];
        $title      = $this->parent->name;
        $icon       = [
            'icon'  => ['fal', 'fa-user'],
            'title' => __('customer')
        ];



        return Inertia::render(
            'Org/Dispatching/DeliveryNotes',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('delivery notes'),
                'pageHead'    => [
                    'title'         => $title,
                    'icon'          => $icon,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions
                ],
                'data'        => DeliveryNotesResource::collection($deliveryNotes),
            ]
        )->table($this->tableStructure(parent: $this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customer);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerClient(Organisation $organisation, Shop $shop, Customer $customer, CustomerHasPlatform $platform, CustomerClient $customerClient, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customerClient;
        $this->customerHasPlatform = $platform;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($customerClient);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomerClient(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, CustomerHasPlatform $customerHasPlatform, CustomerClient $customerClient, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $customerClient;
        $this->customerHasPlatform = $customerHasPlatform;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($customerClient);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Delivery notes'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.show.delivery_notes.index' => array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($this->customerHasPlatform, 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.platforms.show.customer-clients.show.delivery_notes.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show.delivery_notes.index' => array_merge(
                ShowCustomerClient::make()->getBreadcrumbs($this->customerHasPlatform, 'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.fulfilments.show.crm.customers.show.platforms.show.customer-clients.show.delivery_notes.index',
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumb(
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.delivery_notes.index',
                        'parameters' => $routeParameters
                    ]
                )
            )
        };
    }
}
