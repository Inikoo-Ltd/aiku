<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\OrgAction;
use App\Actions\UI\Accounting\Traits\HasPaymentServiceProviderFields;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\PaymentAccount;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPaymentAccount extends OrgAction
{
    use HasPaymentServiceProviderFields;

    public function handle(PaymentAccount $paymentAccount): PaymentAccount
    {
        return $paymentAccount;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, PaymentAccount $paymentAccount, ActionRequest $request): PaymentAccount
    {
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentServiceProvider(Organisation $organisation, OrgPaymentServiceProvider $orgPaymentServiceProvider, PaymentAccount $paymentAccount, ActionRequest $request): PaymentAccount
    {
        $this->initialisation($organisation, $request);

        return $this->handle($paymentAccount);
    }

    public function htmlResponse(PaymentAccount $paymentAccount, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit payment account'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $paymentAccount->code,
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
                            'title'  => __('id'),
                            'fields' => [
                                'code' => [
                                    'type'  => 'input',
                                    'label' => __('code'),
                                    'value' => $paymentAccount->code
                                ],
                                'name' => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $paymentAccount->name
                                ],
                                ...$this->blueprint($paymentAccount->orgPaymentServiceProvider->paymentServiceProvider->slug, $paymentAccount->data)
                            ]
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.org.payment-account.update',
                            'parameters' => [
                                'organisation'   => $paymentAccount->organisation_id,
                                'paymentAccount' => $paymentAccount->id
                            ]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowPaymentAccount::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '(' . __('Editing') . ')'
        );
    }
}
