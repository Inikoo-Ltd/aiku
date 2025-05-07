<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 08:17:53 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\OrgAction;
use App\Enums\UI\Accounting\PaymentTabsEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowPayment extends OrgAction
{
    private Organisation|PaymentAccount|PaymentServiceProvider|Order|Group $parent;


    public function handle(Payment $payment): Payment
    {
        return $payment;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function inOrganisation(Organisation $organisation, Payment $payment, ActionRequest $request): Payment
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        return $this->handle($payment);
    }

    public function inPaymentAccount(Organisation $organisation, PaymentAccount $paymentAccount, Payment $payment, ActionRequest $request): Payment
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        return $this->handle($payment);
    }



    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccountInPaymentServiceProvider(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, PaymentAccount $paymentAccount, Payment $payment, ActionRequest $request): Payment
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        return $this->handle($payment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentServiceProvider(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());
        $this->parent = $paymentServiceProvider;
        return $this->handle($payment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($shop, $request)->withTab(DepartmentTabsEnum::values());

        return $this->handle($payment);
    }

    public function htmlResponse(Payment $payment, ActionRequest $request): Response
    {
        $title = (string) ($payment->reference ?? $payment->id);
        return Inertia::render(
            'Org/Accounting/Payment',
            [
                'title'                                 => $title,
                'breadcrumbs'                           => $this->getBreadcrumbs($payment, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'                            => [
                    'previous' => $this->getPrevious($payment, $request),
                    'next'     => $this->getNext($payment, $request),
                ],
                'pageHead'    => [
                    'model'     => __('payment'),
                    'icon'      => 'fal fa-coins',
                    'title'     => $title,
                    'edit'      => $this->canEdit ? [
                        'route' => [
                            'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                            'parameters' => array_values($request->route()->originalParameters())
                        ]
                    ] : false,

                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => PaymentTabsEnum::navigation()
                ],

                PaymentTabsEnum::SHOWCASE->value => $this->tab == PaymentTabsEnum::SHOWCASE->value ?
                    fn () => GetPaymentShowcase::run($payment)
                    : Inertia::lazy(fn () => GetPaymentShowcase::run($payment)),

            ]
        );
    }


    public function jsonResponse(Payment $payment): PaymentsResource
    {
        return new PaymentsResource($payment);
    }

    public function getBreadcrumbs(Payment $payment, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Payment $payment, array $routeParameters, string $suffix = null) {
            return [
                [

                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Payments')
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $payment->reference ?? __('No reference'),
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.payments.show' => array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs(
                    'grp.org.accounting.dashboard',
                    Arr::only($routeParameters, ['organisation'])
                ),
                $headCrumb(
                    $payment,
                    [
                        'index' => [
                            'name'       => 'grp.org.accounting.payments.index',
                            'parameters' => Arr::only($routeParameters, ['organisation'])
                        ],
                        'model' => [
                            'name'       => 'grp.org.accounting.payments.show',
                            'parameters' => Arr::only($routeParameters, ['organisation', 'payment'])
                        ]
                    ],
                    $suffix
                ),
            ),
            default => []
        };
    }

    public function getPrevious(Payment $payment, ActionRequest $request): ?array
    {
        $previous = Payment::where('id', '<', $payment->id)->when(true, function ($query) use ($payment, $request) {
            switch ($request->route()->getName()) {
                case 'grp.org.accounting.payment-accounts.show.payments.show':
                    $query->where('payments.payment_account_id', $payment->payment_account_id);
                    break;
                case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.show':
                case 'grp.org.accounting.org_payment_service_providers.show.payments.show':
                    $query->where('payment_accounts.payment_account_id', $payment->paymentAccount->payment_service_provider_id);
                    break;

            }
        })->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Payment $payment, ActionRequest $request): ?array
    {
        $next = Payment::where('id', '>', $payment->id)->when(true, function ($query) use ($payment, $request) {
            switch ($request->route()->getName()) {
                case 'grp.org.accounting.payment-accounts.show.payments.show':
                    $query->where('payments.payment_account_id', $payment->paymentAccount->id);
                    break;
                case 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.show':
                case 'grp.org.accounting.org_payment_service_providers.show.payments.show':
                    $query->where('payment_accounts.payment_account_id', $payment->paymentAccount->payment_service_provider_id);
                    break;

            }
        })->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Payment $payment, string $routeName): ?array
    {
        if (!$payment) {
            return null;
        }
        return match ($routeName) {
            'grp.org.accounting.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'organisation' => $payment->organisation->slug,
                        'payment'  => $payment->id
                    ]

                ]
            ],
            'grp.org.accounting.payment-accounts.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'paymentAccount' => $payment->paymentAccount->slug,
                        'payment'       => $payment->id
                    ]

                ]
            ],
            'grp.org.accounting.org_payment_service_providers.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'paymentServiceProvider' => $payment->paymentAccount->paymentServiceProvider->slug,
                        'payment'               => $payment->id
                    ]

                ]
            ],
            'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'paymentServiceProvider' => $payment->paymentAccount->paymentServiceProvider->slug,
                        'paymentAccount'        => $payment->paymentAccount->slug,
                        'payment'               => $payment->id
                    ]

                ]
            ]
        };
    }
}
