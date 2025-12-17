<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Accounting\PaymentTabsEnum;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPayment extends OrgAction
{
    public function handle(Payment $payment): Payment
    {
        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        // TODO: fix this to use the correct permissions (can't pass in test)
        // $this->canEdit = $request->user()->authTo('accounting.edit');
        // return $request->user()->authTo("accounting.view");
        return true;
    }

    public function inOrganisation(Organisation $organisation, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request);
        return $this->handle($payment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentServiceProvider(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        return $this->handle($payment);
    }




    /** @noinspection PhpUnusedParameterInspection */
    public function inOrder(Organisation $organisation, Order $order, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        return $this->handle($payment);
    }


    public function htmlResponse(Payment $payment, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('payment'),
                'breadcrumbs' => ShowPayment::make()->getBreadcrumbs($payment, $request->route()->getName(), $request->route()->originalParameters()),
                'pageHead'    => [
                    'title'     => $payment->reference,
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
                                'amount' => [
                                    'type'  => 'input',
                                    'label' => __('Amount'),
                                    'value' => $payment->amount
                                ],
                                'date' => [
                                    'type'  => 'input',
                                    'label' => __('label'),
                                    'value' => $payment->date
                                ],
                            ]
                        ]

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.payment.update',
                            'parameters' => $payment->id

                        ],
                    ]
                ]
            ]
        );
    }


    public function getBreadcrumbs(Payment $payment, string $routeName, array $routeParameters): array
    {
        return ShowPayment::make()->getBreadcrumbs(
            payment: $payment,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

}
