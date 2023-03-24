<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 15:40:56 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\PaymentAccount\UI;

use App\Actions\InertiaAction;
use App\Http\Resources\Accounting\PaymentAccountResource;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property PaymentAccount $paymentAccount
 */
class ShowPaymentAccount extends InertiaAction
{
    use HasUIPaymentAccount;
    public function handle(PaymentAccount $paymentAccount): PaymentAccount
    {
        return $paymentAccount;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->can('accounting.edit');
        return $request->user()->hasPermissionTo("accounting.view");
    }

    public function asController(PaymentAccount $paymentAccount, ActionRequest $request): PaymentAccount
    {
        $this->routeName = $request->route()->getName();
        //$this->validateAttributes();
        $this->initialisation($request);
        return $this->handle($paymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentServiceProvider(PaymentServiceProvider $paymentServiceProvider, PaymentAccount $paymentAccount, ActionRequest $request): PaymentAccount
    {
        $this->routeName = $request->route()->getName();
        $this->initialisation($request);
        return $this->handle($paymentAccount);
    }

    public function htmlResponse(PaymentAccount $paymentAccount): Response
    {
        return Inertia::render(
            'Accounting/PaymentAccount',
            [
                'title'       => $paymentAccount->name,
                'breadcrumbs' => $this->getBreadcrumbs($this->routeName, $paymentAccount),
                'pageHead'    => [
                    'icon'    => 'fal fa-agent',
                    'title'   => $paymentAccount->slug,
                    'create'  => $this->canEdit && (
                        $this->routeName=='accounting.payment-service-providers.show.payment-accounts.show' or
                        $this->routeName=='accounting.payment-accounts.show'
                    ) ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'show.payments.create', $this->routeName) ,
                            'parameters' => array_values($this->originalParameters)
                        ],
                        'label'=> __('payment')
                    ] : false,
                    'meta'  => [
                        [
                            'name'   => trans_choice('payment | payments', $paymentAccount->stats->number_payments),
                            'number' => $paymentAccount->stats->number_payments,
                            'href'   => match ($this->routeName) {
                                'accounting.payment-service-providers.show.payment-accounts.show' => [
                                    'accounting.payment-service-providers.show.payment-accounts.show.payments.index',
                                    [$paymentAccount->paymentServiceProvider->slug, $paymentAccount->slug]
                                ],
                                default => [
                                    'accounting.payment-accounts.show.payments.index',
                                    $paymentAccount->slug
                                ]
                            },
                            'leftIcon' => [
                                'icon'    => 'fal fa-credit-card',
                                'tooltip' => __('payments')
                            ]
                        ],

                    ],

                ],
                'payment_account' => $paymentAccount
            ]
        );
    }


    public function jsonResponse(PaymentAccount $paymentAccount): PaymentAccountResource
    {
        return new PaymentAccountResource($paymentAccount);
    }
}
