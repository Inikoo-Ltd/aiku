<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser;

use App\Actions\OrgAction;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditWebUser extends OrgAction
{
    private Fulfilment|Shop $parent;
    private Customer|FulfilmentCustomer $scope;

    public function handle(WebUser $webUser): WebUser
    {
        return $webUser;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Fulfilment) {
            return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.view");
        } elseif ($this->parent instanceof Shop) {
            return $request->user()->authTo("crm.{$this->shop->id}.view");
        }

        return false;
    }

    public function asController(Organisation $organisation, Shop $shop, Customer $customer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->parent = $shop;
        $this->scope  = $customer;

        $this->initialisationFromShop($shop, $request);

        return $this->handle($webUser);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, WebUser $webUser, ActionRequest $request): WebUser
    {
        $this->parent = $fulfilment;
        $this->scope  = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($webUser);
    }


    public function htmlResponse(WebUser $webUser, ActionRequest $request): Response
    {
        $scope     = $this->scope;
        $container = null;
        if (class_basename($scope) == 'Customer') {
            $container = [
                'icon'    => ['fal', 'fa-user'],
                'tooltip' => __('Customer'),
                'label'   => Str::possessive($scope->name)
            ];
        } elseif (class_basename($scope) == 'FulfilmentCustomer') {
            $container = [
                'icon'    => ['fal', 'fa-user'],
                'tooltip' => __('Customer'),
                'label'   => Str::possessive($scope->customer->name)
            ];
        }
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('web user'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => __('Edit web user'),
                    'container' => $container,
                    'meta'      => [
                        [
                            'name' => $webUser->username
                        ]
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'   => __('credentials'),
                            'label'   => __('credentials'),
                            'icon'    => 'fa-light fa-key',
                            'current' => true,
                            'fields'  => [
                                'contact_name' => [
                                    'type'  => 'input',
                                    'label' => __('Contact name'),
                                    'value' => $webUser->contact_name
                                ],
                                'email'        => [
                                    'type'  => 'input',
                                    'label' => __('email'),
                                    'value' => $webUser->email
                                ],
                                'username'     => [
                                    'type'  => 'input',
                                    'label' => __('username'),
                                    'value' => $webUser->username
                                ],
                                'password'     => [
                                    'type'                 => 'password',
                                    'label'                => __('Set new password'),
                                    'value'                => '',
                                ],
                                'is_root'      => [
                                    'type'  => 'toggle',
                                    'label' => __('Admin'),
                                    'value' => $webUser->is_root
                                ],

                            ]
                        ],


                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.web-user.update',
                            'parameters' => [$webUser->id]

                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowWebUser::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
