<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class CreatePaymentAccount extends OrgAction
{
    private Shop|Organisation|PaymentServiceProvider $parent;
    public function handle(): Response
    {
        return Inertia::render(
            'CreateModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('New payment account'),
                'pageHead'    => [
                    'title'        => __('New payment account'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.accounting.payment-accounts.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'title'  => __('payment account'),
                            'fields' => [
                                'code' => [
                                    'type'     => 'input',
                                    'label'    => __('code'),
                                    'required' => true
                                ],
                                'name' => [
                                    'type'     => 'input',
                                    'label'    => __('name'),
                                    'required' => true
                                ],
                                'type' => [
                                    'type'     => 'select',
                                    'label'    => __('Payment Provider'),
                                    'required' => true,
                                    'options'  => Options::forEnum(PaymentAccountTypeEnum::class)
                                ],
                                'checkout_access_key' => [
                                    'type'     => 'input',
                                    'label'    => __('access key'),
                                    'required' => true
                                ],
                                'checkout_secret_key' => [
                                    'type'     => 'input',
                                    'label'    => __('secret key'),
                                    'required' => true
                                ],
                                'checkout_channel_id' => [
                                    'type'     => 'input',
                                    'label'    => __('channel id'),
                                    'required' => true
                                ]
                            ]
                        ]
                    ],
                    'route'      => [
                        'name'       => 'grp.models.org.payment-account.store',
                        'parameters' => [
                            'organisation' => $this->organisation->id
                        ]
                    ]
                ],


            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Organisation) {
            return $request->user()->authTo("accounting.{$this->organisation->id}.view");
        } elseif ($this->parent instanceof Shop) {
            //todo think about it
            return false;
        }

        return false;
    }


    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle();
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            IndexPaymentAccounts::make()->getBreadcrumbs(
                request()->route()->getName(),
                request()->route()->parameters
            ),
            [
                [
                    'type'          => 'creatingModel',
                    'creatingModel' => [
                        'label' => __("creating payment account"),
                    ]
                ]
            ]
        );
    }
}
