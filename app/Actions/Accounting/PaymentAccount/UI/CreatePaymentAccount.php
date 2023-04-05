<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\InertiaAction;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Central\Tenant;
use App\Models\Marketing\Shop;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class CreatePaymentAccount extends InertiaAction
{
    private Shop|Tenant|PaymentServiceProvider $parent;
    public function handle(): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs($this->routeName, $this->parent),
                'title'       => __('new payment account'),
                'pageHead'    => [
                    'title'        => __('new payment account'),
                    'cancelCreate' => [
                        'route' => [
                            'name'       => 'accounting.payment-accounts.index',
                            'parameters' => array_values($this->originalParameters)
                        ],
                    ]

                ],


            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->can('accounting.edit');
    }


    public function asController(ActionRequest $request): Response
    {
        $this->initialisation($request);
        $this->parent = app('currentTenant');
        return $this->handle();
    }

    public function getBreadcrumbs(string $routeName, Shop|Tenant|PaymentServiceProvider $parent): array
    {
        return array_merge(
            IndexPaymentAccounts::make()->getBreadcrumbs($routeName, $parent),
            [
                [
                    'suffix'=> __('creating payment account')
                ]
            ]
        );
    }
}
