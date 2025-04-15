<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Http\Resources\CRM\WebUserResource;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebUser extends OrgAction
{
    use WithAuthorizeWebUserScope;
    use WithFulfilmentCustomerSubNavigation;


    private FulfilmentCustomer|Customer $parent;

    public function handle(WebUser $webUser): WebUser
    {
        return $webUser;
    }


    public function authorize(ActionRequest $request): bool
    {
        return $this->authorizeWebUserScope($request);
    }



    public function asController(Organisation $organisation, Shop $shop, Customer $customer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->parent = $customer;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($webUser);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webUser);
    }


    public function htmlResponse(WebUser $webUser, ActionRequest $request): Response
    {
        $model = __('web user');
        $iconRight  = [];
        $subNavigation = [];

        if ($this->parent instanceof  FulfilmentCustomer) {
            $model = $this->parent->customer->name;
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
            $iconRight = [
                'icon' => 'fal fa-terminal',
            ];
        }


        return Inertia::render(
            'Org/Shop/CRM/WebUser',
            [
                'title'       => __('Web user'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'           => [
                    'previous' => $this->getPrevious($webUser, $request),
                    'next'     => $this->getNext($webUser, $request),
                ],
                'pageHead'    => [
                    'model'         => $model,
                    'title'         => $webUser->username,
                    'noCapitalise'  => true,
                    'subNavigation' => $subNavigation,
                    'icon'              => 'fal fa-user',
                    'iconRight' => $iconRight,
                // 'meta'          => [
                    //     [
                    //         'name' => $webUser->username
                    //     ]
                    // ],
                    'actions'   => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                                'parameters' => $request->route()->originalParameters()
                            ]
                        ] : false,

                    ],

                ],
                'data'     => new WebUserResource($webUser)
            ]
        );
    }


    public function jsonResponse(WebUser $webUser): WebUserResource
    {
        return new WebUserResource($webUser);
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (WebUser $webUser, array $routeParameters, string $suffix) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Web users')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $webUser->username,
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $webUser = WebUser::where('slug', $routeParameters['webUser'])->first();

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.web-users.show' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs(
                    Arr::except($routeParameters, 'webUser')
                ),
                $headCrumb(
                    $webUser,
                    [
                        'index' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.web-users.index',
                            'parameters' => Arr::except($routeParameters, 'webUser')
                        ],
                        'model' => [
                            'name'       => 'grp.org.fulfilments.show.crm.customers.show.web-users.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),

            'grp.org.shops.show.crm.customers.show.web-users.show' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'grp.org.shops.show.crm.customers.show',
                    Arr::except($routeParameters, 'webUser')
                ),
                $headCrumb(
                    $webUser,
                    [
                        'index' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.web-users.index',
                            'parameters' => Arr::except($routeParameters, 'webUser')
                        ],
                        'model' => [
                            'name'       => 'grp.org.shops.show.crm.customers.show.web-users.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),



            default => []
        };
    }

    public function getPrevious(WebUser $webUser, ActionRequest $request): ?array
    {
        $previous = WebUser::where('slug', '<', $webUser->slug)
            ->where('web_users.customer_id', $webUser->customer_id)
            ->orderBy('slug', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(WebUser $webUser, ActionRequest $request): ?array
    {
        $next = WebUser::where('slug', '>', $webUser->slug)
            ->where('web_users.customer_id', $webUser->customer_id)
            ->orderBy('slug')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?WebUser $webUser, string $routeName): ?array
    {
        if (!$webUser) {
            return null;
        }

        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.web-users.show' => [
                'label' => $webUser->username,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $webUser->organisation->slug,
                        'fulfilment'      => $webUser->shop->fulfilment->slug,
                        'fulfilmentCustomer' => $webUser->customer->fulfilmentCustomer->slug,
                        'webUser' => $webUser->slug
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.web-users.show' => [
                'label' => $webUser->username,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $webUser->organisation->slug,
                        'shop'      => $webUser->shop->slug,
                        'customer' => $webUser->customer->slug,
                        'webUser' => $webUser->slug
                    ]

                ]
            ],
            default => []
        };
    }


}
