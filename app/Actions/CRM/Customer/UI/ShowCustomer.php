<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\UI;

use App\Actions\Accounting\CreditTransaction\UI\IndexCreditTransactions;
use App\Actions\Catalogue\Shop\UI\ShowShop;
use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\CRM\BackInStockReminder\UI\IndexCustomerBackInStockReminders;
use App\Actions\CRM\Favourite\UI\IndexCustomerFavourites;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Helpers\Media\UI\IndexAttachments;
use App\Actions\Ordering\Order\UI\IndexOrders;
use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Actions\Traits\WithWebUserMeta;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\UI\CRM\CustomerDropshippingTabsEnum;
use App\Enums\UI\CRM\CustomerTabsEnum;
use App\Http\Resources\Accounting\CreditTransactionsResource;
use App\Http\Resources\CRM\CustomerBackInStockRemindersResource;
use App\Http\Resources\CRM\CustomerFavouritesResource;
use App\Http\Resources\CRM\CustomersResource;
use App\Http\Resources\Helpers\Attachment\AttachmentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowCustomer extends OrgAction
{
    use WithActionButtons;
    use WithWebUserMeta;
    use WithCustomerSubNavigation;
    use WithCRMAuthorisation;

    private Organisation|Shop $parent;

    public function handle(Customer $customer): Customer
    {
        return $customer;
    }

    public function inOrganisation(Organisation $organisation, Customer $customer, ActionRequest $request): Customer
    {

        $this->parent = $organisation;
        $this->initialisation($organisation, $request)
            ->withTab($customer->shop->type == ShopTypeEnum::DROPSHIPPING ? CustomerDropshippingTabsEnum::values() : CustomerTabsEnum::values());

        return $this->handle($customer);
    }


    public function asController(Organisation $organisation, Shop $shop, Customer $customer, ActionRequest $request): Customer
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request)
            ->withTab($customer->shop->type == ShopTypeEnum::DROPSHIPPING ? CustomerDropshippingTabsEnum::values() : CustomerTabsEnum::values());

        return $this->handle($customer);
    }

    public function htmlResponse(Customer $customer, ActionRequest $request): Response
    {
        $tabs = $customer->shop->type == ShopTypeEnum::DROPSHIPPING ? CustomerDropshippingTabsEnum::class : CustomerTabsEnum::class;
        $navigation = $tabs::navigation();
        $webUsersMeta = $this->getWebUserMeta($customer, $request);

        $shopMeta      = [];
        $subNavigation = null;
        if ($this->parent instanceof Shop) {
            if ($this->parent->type == ShopTypeEnum::DROPSHIPPING) {
                $subNavigation = $this->getCustomerDropshippingSubNavigation($customer, $request);
                unset($navigation[$tabs::FAVOURITES->value]);
                unset($navigation[$tabs::REMINDERS->value]);
            } else {
                $subNavigation = $this->getCustomerSubNavigation($customer, $request);
            }
        }

        if ($request->route()->getName() == 'customers.show') {
            $shopMeta = [
                'route'    => ['shops.show', $customer->shop->slug],
                'name'     => $customer->shop->code,
                'leftIcon' => [
                    'icon'    => 'fal fa-store-alt',
                    'tooltip' => __('Shop'),
                ],
            ];
        }

        return Inertia::render(
            'Org/Shop/CRM/Customer',
            [
                'title'            => __('customer'),
                'breadcrumbs'      => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'       => [
                    'previous' => $this->getPrevious($customer, $request),
                    'next'     => $this->getNext($customer, $request),
                ],
                'pageHead'         => [
                    'title'         => $customer->name,
                    'icon'          => [
                        'icon'  => ['fal', 'fa-user'],
                        'title' => __('customer')
                    ],
                    'afterTitle'    => [
                        'label' => '#'.$customer->reference,
                    ],
                    'meta'          => array_filter([
                        $shopMeta,
                        $webUsersMeta
                    ]),
                    'actions'       => [
                        [
                            'type'    => 'button',
                            'style'   => 'edit',
                            'tooltip' => __('Edit Customer'),
                            // 'label'   => __('Edit Customer'),
                            'route'   => [
                                'name'       => 'grp.org.shops.show.crm.customers.edit',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ],
                    ],
                    'subNavigation' => $subNavigation,
                ],
                'attachmentRoutes' => [
                    'attachRoute' => [
                        'name'       => 'grp.models.customer.attachment.attach',
                        'parameters' => [
                            'customer' => $customer->id,
                        ]
                    ],
                    'detachRoute' => [
                        'name'       => 'grp.models.customer.attachment.detach',
                        'parameters' => [
                            'customer' => $customer->id,
                        ],
                        'method'     => 'delete'
                    ]
                ],
                'tabs'             => [
                    'current'    => $this->tab,
                    'navigation' => $navigation

                ],
                'accounting'       => [
                    'balance'             => $customer->balance,
                    'credit_transactions' => $customer->stats->number_credit_transactions
                ],

                $tabs::SHOWCASE->value    => $this->tab == $tabs::SHOWCASE->value ?
                    fn () => GetCustomerShowcase::run($customer)
                    : Inertia::lazy(fn () => GetCustomerShowcase::run($customer)),


                /*
                $tabs::PRODUCTS->value => $this->tab == $tabs::PRODUCTS->value ?
                    fn () => ProductsResource::collection(IndexDropshippingRetinaProducts::run($customer))
                    : Inertia::lazy(fn () => ProductsResource::collection(IndexDropshippingRetinaProducts::run($customer))),
                */

                // $tabs::DISPATCHED_EMAILS->value => $this->tab == $tabs::DISPATCHED_EMAILS->value ?
                //     fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($customer))
                //     : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($customer))),
                $tabs::CREDIT_TRANSACTIONS->value  => $this->tab == $tabs::CREDIT_TRANSACTIONS->value ?
                    fn () => CreditTransactionsResource::collection(IndexCreditTransactions::run($customer))
                    : Inertia::lazy(fn () => CreditTransactionsResource::collection(IndexCreditTransactions::run($customer))),
                $tabs::FAVOURITES->value  => $this->tab == $tabs::FAVOURITES->value ?
                    fn () => CustomerFavouritesResource::collection(IndexCustomerFavourites::run($customer))
                    : Inertia::lazy(fn () => CustomerFavouritesResource::collection(IndexCustomerFavourites::run($customer))),
                $tabs::REMINDERS->value   => $this->tab == $tabs::REMINDERS->value ?
                    fn () => CustomerBackInStockRemindersResource::collection(IndexCustomerBackInStockReminders::run($customer))
                    : Inertia::lazy(fn () => CustomerBackInStockRemindersResource::collection(IndexCustomerBackInStockReminders::run($customer))),
                $tabs::ATTACHMENTS->value => $this->tab == $tabs::ATTACHMENTS->value ?
                    fn () => AttachmentsResource::collection(IndexAttachments::run($customer))
                    : Inertia::lazy(fn () => AttachmentsResource::collection(IndexAttachments::run($customer))),
                $tabs::HISTORY->value => $this->tab == $tabs::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($customer))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($customer))),


            ]
        )->table(IndexOrders::make()->tableStructure($customer))
            //    ->table(IndexDropshippingRetinaProducts::make()->tableStructure($customer))
            ->table(IndexCustomerFavourites::make()->tableStructure(parent:$customer, prefix:$tabs::FAVOURITES->value))
            ->table(IndexCustomerBackInStockReminders::make()->tableStructure($customer, $tabs::REMINDERS->value))
            ->table(IndexAttachments::make()->tableStructure($tabs::ATTACHMENTS->value))
            ->table(IndexDispatchedEmails::make()->tableStructure($customer))
            ->table(IndexCreditTransactions::make()->tableStructure($customer, $tabs::CREDIT_TRANSACTIONS->value))
            ->table(IndexHistory::make()->tableStructure($tabs::HISTORY->value));
    }


    public function jsonResponse(Customer $customer): CustomersResource
    {
        return new CustomersResource($customer);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Customer $customer, array $routeParameters, string $suffix = null) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Customers')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $customer->name,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $customer = Customer::where('slug', $routeParameters['customer'])->first();

        return match ($routeName) {
            'grp.org.customers.show',
            => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $customer,
                    [
                        'index' => [
                            'name'       => 'grp.org.customers.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.customers.customers.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'customer'])
                        ]
                    ],
                    $suffix
                ),
            ),

            'grp.org.shops.show.crm.customers.show',
            'grp.org.shops.show.crm.customers.edit',
            'grp.org.shops.show.crm.customers.show.platforms.index',
            'grp.org.shops.show.crm.customers.show.platforms.show',
            'grp.org.shops.show.crm.customers.show.platforms.show.portfolios.index',
            'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.manual.index',
            'grp.org.shops.show.crm.customers.show.platforms.show.customer_clients.other_platform.index',
            'grp.org.shops.show.crm.customers.show.platforms.show.orders.index'
            => array_merge(
                ShowShop::make()->getBreadcrumbs(Arr::only($routeParameters, ['organisation', 'shop'])),
                $headCrumb(
                    $customer,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.index',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'shop', 'customer'])
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(Customer $customer, ActionRequest $request): ?array
    {
        $previous = Customer::where('slug', '<', $customer->slug)->when(
            true,
            function ($query) use ($customer, $request) {
                if ($request->route()->getName() == 'shops.show.customers.show') {
                    $query->where('customers.shop_id', $customer->shop_id);
                }
            }
        )->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(Customer $customer, ActionRequest $request): ?array
    {
        $next = Customer::where('slug', '>', $customer->slug)->when(true, function ($query) {
            if ($this->parent instanceof Organisation) {
                $query->where('customers.organisation_id', $this->parent->id);
            } elseif ($this->parent instanceof Shop) {
                $query->where('customers.shop_id', $this->parent->id);
            }
        })->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Customer $customer, string $routeName): ?array
    {
        if (!$customer) {
            return null;
        }

        return match ($routeName) {
            'grp.org.customers.show' => [
                'label' => $customer->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $customer->organisation->slug,
                        'customer'     => $customer->slug
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show' => [
                'label' => $customer->name,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $customer->organisation->slug,
                        'shop'         => $customer->shop->slug,
                        'customer'     => $customer->slug
                    ]

                ]
            ]
        };
    }
}
