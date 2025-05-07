<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 20:00:07 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrgPaymentServiceProvider\UI;

use App\Actions\Accounting\Invoice\UI\IndexInvoices;
use App\Actions\Accounting\Payment\UI\IndexPayments;
use App\Actions\Accounting\PaymentAccount\UI\IndexPaymentAccounts;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Enums\UI\Accounting\OrgPaymentServiceProviderTabsEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\Http\Resources\Accounting\OrgPaymentServiceProviderResource;
use App\Http\Resources\Accounting\PaymentAccountsResource;
use App\Http\Resources\Accounting\PaymentsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowOrgPaymentServiceProvider extends OrgAction
{
    public function handle(OrgPaymentServiceProvider $orgPaymentServiceProvider): OrgPaymentServiceProvider
    {
        return $orgPaymentServiceProvider;
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit   = $request->user()->authTo("accounting.{$this->organisation->id}.edit");
        $this->canDelete = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    public function asController(Organisation $organisation, OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): OrgPaymentServiceProvider
    {
        $this->initialisation($organisation, $request)->withTab(OrgPaymentServiceProviderTabsEnum::values());

        return $this->handle($orgPaymentServiceProvider);
    }

    public function htmlResponse(OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/OrgPaymentServiceProvider',
            [
                'title'       => __('payment service provider'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->originalParameters()
                ),

                'navigation'                                    => [
                    'previous' => $this->getPrevious($orgPaymentServiceProvider, $request),
                    'next'     => $this->getNext($orgPaymentServiceProvider, $request),
                ],
                'pageHead'                                      => [
                    'icon'  =>
                        [
                            'icon'  => ['fal', 'fa-cash-register'],
                            'title' => __('payment service provider')
                        ],
                    'title'     => $orgPaymentServiceProvider->slug,
                    'model'     => __('payment service provider').':',
                    'meta'      => [
                        [
                            'name'     => trans_choice('account | accounts', $orgPaymentServiceProvider->stats->number_payment_accounts),
                            'number'   => $orgPaymentServiceProvider->stats->number_payment_accounts,
                            'route'     => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.show.payment-accounts.index',
                                'parameters' => [
                                    $this->organisation->slug,
                                    $orgPaymentServiceProvider->slug
                                ]
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-money-check-alt',
                                'tooltip' => __('accounts')
                            ]
                        ],
                        [
                            'name'     => trans_choice('payment | payments', $orgPaymentServiceProvider->stats->number_payments),
                            'number'   => $orgPaymentServiceProvider->stats->number_payments,
                            'route'     => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.show.payments.index',
                                'parameters' => [
                                    $this->organisation->slug,
                                    $orgPaymentServiceProvider->slug
                                ]
                            ],
                            'leftIcon' => [
                                'icon'    => 'fal fa-coins',
                                'tooltip' => __('payments')
                            ]
                        ]
                    ]
                ],
                'tabs'                                          => [
                    'current'    => $this->tab,
                    'navigation' => OrgPaymentServiceProviderTabsEnum::navigation()
                ],
                OrgPaymentServiceProviderTabsEnum::SHOWCASE->value => $this->tab == OrgPaymentServiceProviderTabsEnum::SHOWCASE->value ?
                    fn () => GetOrgPaymentServiceProviderShowcase::run($orgPaymentServiceProvider)
                    : Inertia::lazy(fn () => GetOrgPaymentServiceProviderShowcase::run($orgPaymentServiceProvider)),

                OrgPaymentServiceProviderTabsEnum::PAYMENTS->value         => $this->tab == OrgPaymentServiceProviderTabsEnum::PAYMENTS->value
                    ?
                    fn () => PaymentsResource::collection(
                        IndexPayments::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::PAYMENTS->value
                        )
                    )
                    : Inertia::lazy(fn () => PaymentsResource::collection(
                        IndexPayments::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::PAYMENTS->value
                        )
                    )),
                OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS->value => $this->tab == OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS->value
                    ?
                    fn () => PaymentAccountsResource::collection(
                        IndexPaymentAccounts::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS->value
                        )
                    )
                    : Inertia::lazy(fn () => PaymentAccountsResource::collection(
                        IndexPaymentAccounts::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS->value
                        )
                    )),
                OrgPaymentServiceProviderTabsEnum::INVOICES->value => $this->tab == OrgPaymentServiceProviderTabsEnum::INVOICES->value
                    ?
                    fn () => InvoicesResource::collection(
                        IndexInvoices::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::INVOICES->value
                        )
                    )
                    : Inertia::lazy(fn () => InvoicesResource::collection(
                        IndexInvoices::run(
                            parent: $orgPaymentServiceProvider,
                            prefix: OrgPaymentServiceProviderTabsEnum::INVOICES->value
                        )
                    )),

                OrgPaymentServiceProviderTabsEnum::HISTORY->value => $this->tab == OrgPaymentServiceProviderTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($orgPaymentServiceProvider))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($orgPaymentServiceProvider)))
            ]
        )
            ->table(
                IndexPayments::make()->tableStructure(
                    parent: $orgPaymentServiceProvider,
                    modelOperations: [
                        'createLink' => $this->canEdit ? [
                            'route' => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.show.payments.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                            'label' => __('payment')
                        ] : false,
                    ],
                    prefix: OrgPaymentServiceProviderTabsEnum::PAYMENTS->value
                )
            )
            ->table(
                IndexPaymentAccounts::make()->tableStructure(
                    parent: $orgPaymentServiceProvider,
                    prefix: OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS->value
                )
            )
            ->table(IndexInvoices::make()->tableStructure(
                parent: $orgPaymentServiceProvider,
                prefix:OrgPaymentServiceProviderTabsEnum::INVOICES->value
            ))
            ->table(IndexHistory::make()->tableStructure(
                prefix:OrgPaymentServiceProviderTabsEnum::HISTORY->value
            ));
    }

    public function jsonResponse(OrgPaymentServiceProvider $orgPaymentServiceProvider): OrgPaymentServiceProviderResource
    {
        return new OrgPaymentServiceProviderResource($orgPaymentServiceProvider);
    }

    public function getBreadcrumbs(array $routeParameters, $suffix = null): array
    {
        $orgPaymentServiceProvider = OrgPaymentServiceProvider::where('slug', $routeParameters['orgPaymentServiceProvider'])->first();

        return array_merge(
            ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', Arr::only($routeParameters, 'organisation')),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.index',
                                'parameters' => Arr::only($routeParameters, 'organisation')
                            ],
                            'label' => __('Providers')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.accounting.org_payment_service_providers.show',
                                'parameters' => $routeParameters
                            ],
                            'label' => $orgPaymentServiceProvider->slug,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getPrevious(OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): ?array
    {
        $previous = OrgPaymentServiceProvider::where('code', '<', $orgPaymentServiceProvider->code)
            ->where('organisation_id', $orgPaymentServiceProvider->organisation_id)
            ->orderBy('code', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());
    }

    public function getNext(OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): ?array
    {
        $next = OrgPaymentServiceProvider::where('code', '>', $orgPaymentServiceProvider->code)
            ->where('organisation_id', $orgPaymentServiceProvider->organisation_id)
            ->orderBy('code')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?OrgPaymentServiceProvider $orgPaymentServiceProvider, string $routeName): ?array
    {
        if (!$orgPaymentServiceProvider) {
            return null;
        }

        return match ($routeName) {
            'grp.org.accounting.org_payment_service_providers.show' => [
                'label' => $orgPaymentServiceProvider->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'organisation'              => $orgPaymentServiceProvider->organisation->slug,
                        'orgPaymentServiceProvider' => $orgPaymentServiceProvider->slug
                    ]

                ]
            ]
        };
    }
}
