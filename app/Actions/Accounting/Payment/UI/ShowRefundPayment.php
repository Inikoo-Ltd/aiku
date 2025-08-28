<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 08:17:53 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\CRM\Customer\UI\ShowCustomer;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingAuthorisation;
use App\Enums\UI\Accounting\PaymentTabsEnum;
use App\Enums\UI\Catalogue\DepartmentTabsEnum;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Str;

class ShowRefundPayment extends OrgAction
{
    use WithAccountingAuthorisation;

    public function handle(Payment $payment): Payment
    {
        return $payment;
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inCustomer(Organisation $organisation, Shop $shop, Customer $customer, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisationFromShop($shop, $request)->withTab(PaymentTabsEnum::values());

        return $this->handle($payment);
    }

    public function inOrganisation(Organisation $organisation, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());

        return $this->handle($payment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccount(Organisation $organisation, PaymentAccount $paymentAccount, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());

        return $this->handle($payment);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccountInPaymentServiceProvider(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, PaymentAccount $paymentAccount, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());

        return $this->handle($payment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentServiceProvider(Organisation $organisation, PaymentServiceProvider $paymentServiceProvider, Payment $payment, ActionRequest $request): Payment
    {
        $this->initialisation($organisation, $request)->withTab(PaymentTabsEnum::values());

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
        $title = (string)($payment->reference ?? $payment->id);

        return Inertia::render(
            'Org/Accounting/Payment',
            [
                'title'       => $title,
                'breadcrumbs' => $this->getBreadcrumbs($payment, $request->route()->getName(), $request->route()->originalParameters()),
                'navigation'  => [
                    'previous' => $this->getPrevious($payment, $request),
                    'next'     => $this->getNext($payment, $request),
                ],
                'pageHead'    => [
                    'model' => __('payment'),
                    'icon'  => 'fal fa-coins',
                    'title' => $title,
                    'edit'  => $this->canEdit ? [
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

                'refund_route' => [
                    'name' => 'grp.models.org.payment_refund.store',
                    'parameters' => [
                        'organisation' => $payment->organisation_id,
                        'payment' => $payment->id
                    ]
                ],

                PaymentTabsEnum::SHOWCASE->value => $this->tab == PaymentTabsEnum::SHOWCASE->value ?
                    fn () => GetPaymentShowcase::run($payment)
                    : Inertia::lazy(fn () => GetPaymentShowcase::run($payment)),

                PaymentTabsEnum::REFUNDS->value => $this->tab == PaymentTabsEnum::REFUNDS->value ?
                    fn () => PaymentsResource::collection(IndexRefundPayments::run($payment, PaymentTabsEnum::REFUNDS->value))
                    : Inertia::lazy(fn () => PaymentsResource::collection(IndexRefundPayments::run($payment, PaymentTabsEnum::REFUNDS->value))),

                PaymentTabsEnum::HISTORY_NOTES->value => $this->tab == PaymentTabsEnum::HISTORY_NOTES->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($payment, PaymentTabsEnum::HISTORY_NOTES->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($payment, PaymentTabsEnum::HISTORY_NOTES->value))),

            ]
        )->table(IndexRefundPayments::make()->tableStructure($payment, [], PaymentTabsEnum::REFUNDS->value))
            ->table(IndexHistory::make()->tableStructure(PaymentTabsEnum::HISTORY_NOTES->value));
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
                            'label' => $payment->reference ? Str::limit($payment->reference, 12, '...') : __('No reference'),
                        ],

                    ],
                    'suffix'         => $suffix

                ],
            ];
        };

        $headCrumbSimple = function (Payment $payment, array $routeParameters, string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Payment').': '.($payment->reference ? Str::limit($payment->reference, 12, '...') : __('No reference')),
                    ],
                    'suffix' => $suffix
                ]
            ];
        };

        return match ($routeName) {
            'grp.org.shops.show.crm.customers.show.refunds.show' => array_merge(
                ShowCustomer::make()->getBreadcrumbs('grp.org.shops.show.crm.customers.show', $routeParameters),
                $headCrumbSimple(
                    $payment,
                    [
                        'name'       => 'grp.org.shops.show.crm.customers.show.refunds.show',
                        'parameters' => Arr::only($routeParameters, ['organisation','shop','customer', 'payment'])
                    ],
                )
            ),


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
                case 'grp.org.shops.show.crm.customers.show.payments.show':
                    $query->where('payments.customer_id', $payment->customer_id);
                    break;
                default:
                    $query->where('payments.group_id', $payment->group_id);
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
                case 'grp.org.shops.show.crm.customers.show.payments.show':
                    $query->where('payments.customer_id', $payment->customer_id);
                    break;
                default:
                    $query->where('payments.group_id', $payment->group_id);
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
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $payment->organisation->slug,
                        'payment'      => $payment->id
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.refunds.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $payment->organisation->slug,
                        'shop'        => $payment->shop->slug,
                        'customer'        => $payment->customer->slug,
                        'payment'        => $payment->id,
                    ]
                ]
            ],
            'grp.org.accounting.org_payment_service_providers.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'paymentServiceProvider' => $payment->paymentAccount->paymentServiceProvider->slug,
                        'payment'                => $payment->id
                    ]

                ]
            ],
            'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'paymentServiceProvider' => $payment->paymentAccount->paymentServiceProvider->slug,
                        'paymentAccount'         => $payment->paymentAccount->slug,
                        'payment'                => $payment->id
                    ]

                ]
            ],
            'grp.org.shops.show.crm.customers.show.payments.show' => [
                'label' => $payment->reference,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation' => $payment->organisation->slug,
                        'shop'         => $payment->shop->slug,
                        'customer'     => $payment->customer->slug,
                        'payment'      => $payment->id
                    ]

                ]
            ],
        };
    }
}
