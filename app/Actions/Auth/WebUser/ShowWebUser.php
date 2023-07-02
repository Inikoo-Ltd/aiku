<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jul 2023 12:56:22 Malaysia Time, Sanur, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Auth\WebUser;

use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\InertiaAction;
use App\Actions\Market\Shop\UI\ShowShop;
use App\Http\Resources\Auth\WebUserResource;
use App\Models\Auth\WebUser;
use App\Models\CRM\Customer;
use App\Models\Market\Shop;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowWebUser extends InertiaAction
{
    public function handle(WebUser $webUser): WebUser
    {
        return $webUser;
    }


    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->can('crm.customers.edit');
        $this->canDelete = $request->user()->can('crm.customers.edit');
        return $request->user()->hasPermissionTo("crm.customers.view");
    }

    public function inTenant(WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->initialisation($request);

        return $this->handle($webUser);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerInShop(Shop $shop, Customer $customer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->initialisation($request);

        return $this->handle($webUser);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomerInTenant(Customer $customer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->initialisation($request);

        return $this->handle($webUser);
    }


    public function htmlResponse(WebUser $webUser, ActionRequest $request): Response
    {
        //dd($request->route()->getName());
        $scope = match ($request->route()->getName()) {
            'crm.customers.show.web-users.show' => $request->route()->parameters()['customer'],
            default                             => app('currentTenant')
        };

        $container = null;
        if (class_basename($scope) == 'Customer') {
            $container = [
                'icon'    => ['fal', 'fa-user'],
                'tooltip' => __('Customer'),
                'label'   => Str::possessive($scope->name)
            ];
        }

        return Inertia::render(
            'Web/WebUser',
            [
                'title'       => __('Web user'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters
                ),
                'pageHead'    => [
                    'title'     => __('web user'),
                    'container' => $container,
                    'meta'      => [
                        [
                            'name' => $webUser->username
                        ]
                    ],
                    'actions' => [
                        $this->canEdit ? [
                            'type'  => 'button',
                            'style' => 'edit',
                            'route' => [
                                'name'       => preg_replace('/show$/', 'edit', $this->routeName),
                                'parameters' => array_values($this->originalParameters)
                            ]
                        ] : false,

                    ],

                ],
                'webUser'     => new WebUserResource($webUser)
            ]
        );
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->fillFromRequest($request);

        $this->set('canEdit', $request->user()->can('hr.edit'));
        $this->set('canViewUsers', $request->user()->can('users.view'));
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
                            'label' => __('web user')
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

        return match ($routeName) {
            'crm.customers.show.web-users.show' =>
            array_merge(
                ShowCustomer::make()->getBreadcrumbs(
                    'crm.customers.show',
                    ['customer' => $routeParameters['customer']]
                ),
                $headCrumb(
                    $routeParameters['webUser'],
                    [
                        'index' => [
                            'name'       => 'crm.customers.show.web-users.index',
                            'parameters' => [$routeParameters['customer']->slug]
                        ],
                        'model' => [
                            'name'       => 'crm.customers.show.web-users.show',
                            'parameters' => $routeParameters
                        ]
                    ],
                    $suffix
                ),
            ),


            'shops.show.customers.show',
            'shops.show.customers.edit'
            => array_merge(
                (new ShowShop())->getBreadcrumbs($routeParameters),
                $headCrumb(
                    $routeParameters['customer'],
                    [
                        'index' => [
                            'name'       => 'shops.show.customers.index',
                            'parameters' => [
                                $routeParameters['shop']->slug,
                            ]
                        ],
                        'model' => [
                            'name'       => 'shops.show.customers.show',
                            'parameters' => [
                                $routeParameters['shop']->slug,
                                $routeParameters['customer']->slug
                            ]
                        ]
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }


}
