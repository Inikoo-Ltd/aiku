<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 08:17:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\OrgPaymentServiceProvider\UI\ShowOrgPaymentServiceProvider;
use App\Actions\Accounting\PaymentAccount\UI\ShowPaymentAccount;
use App\Actions\Accounting\PaymentAccount\WithPaymentAccountSubNavigation;
use App\Actions\Accounting\UI\ShowAccountingDashboard;
use App\Actions\Comms\Traits\WithAccountingSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Http\Resources\Accounting\PaymentsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexPayments extends OrgAction
{
    use WithPaymentAccountSubNavigation;
    use WithAccountingSubNavigation;

    private Fulfilment|Group|Organisation|PaymentAccount|Shop|OrgPaymentServiceProvider|Invoice $parent;

    public function handle(Group|Fulfilment|Organisation|PaymentAccount|Shop|OrgPaymentServiceProvider|Invoice|Order $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('payment.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $queryBuilder = QueryBuilder::for(Payment::class);
        if (class_basename($parent) == 'Organisation') {
            $queryBuilder->where('payments.organisation_id', $parent->id);
        } elseif (class_basename($parent) == 'OrgPaymentServiceProvider') {
            $queryBuilder->where('payments.org_payment_service_provider_id', $parent->id);
        } elseif (class_basename($parent) == 'PaymentAccount') {
            $queryBuilder->where('payments.payment_account_id', $parent->id);
        } elseif (class_basename($parent) == 'Shop') {
            $queryBuilder->where('payments.shop_id', $parent->id);
        } elseif (class_basename($parent) == 'Group') {
            $queryBuilder->where('payments.group_id', $parent->id);
        } elseif (class_basename($parent) == 'Fulfilment') {
            $queryBuilder->where('payments.shop_id', $parent->shop->id);
        } elseif (class_basename($parent) == 'Invoice') {

            $queryBuilder->leftJoin('model_has_payments', 'payments.id', 'model_has_payments.payment_id')
                ->where('model_has_payments.model_id', $parent->id)
                ->where('model_has_payments.model_type', 'Invoice');
        } elseif (class_basename($parent) == 'Order') {
            $queryBuilder->leftJoin('model_has_payments', 'payments.id', 'model_has_payments.payment_id')
                ->where('model_has_payments.model_id', $parent->id)
                ->where('model_has_payments.model_type', 'Order');
        } else {
            abort(422);
        }

        $queryBuilder->leftjoin('organisations', 'payments.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftjoin('shops', 'payments.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('currencies', 'payments.currency_id', 'currencies.id');

        if (!($parent instanceof Order || $parent instanceof Invoice)) {
            foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                $queryBuilder->whereElementGroup(
                    key: $key,
                    allowedElements: array_keys($elementGroup['elements']),
                    engine: $elementGroup['engine'],
                    prefix: $prefix
                );
            }
        }

        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'payments.id',
                'payments.reference',
                'payments.type',
                'payments.status',
                'payments.date',
                'payments.amount',
                'payment_accounts.name as payment_account_name',
                'payment_accounts.slug as payment_accounts_slug',
                'payment_service_providers.slug as payment_service_providers_slug',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'currencies.code as currency_code',
            ])
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->allowedSorts(['reference', 'status', 'type' ,'date'])
            ->withBetweenDates(['date'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    protected function getElementGroups(Group|Organisation|PaymentAccount|Shop|Fulfilment|OrgPaymentServiceProvider $parent): array
    {

        return [

            'state' => [
                'label'    => __('State'),
                'elements' => array_merge_recursive(
                    PaymentStateEnum::labels(),
                    PaymentStateEnum::count($parent),
                    PaymentStateEnum::shortLabels(),
                ),

                'engine' => function ($query, $elements) {
                    $query->whereIn('payments.state', $elements);
                }

            ],
        ];
    }

    public function tableStructure(Group|Fulfilment|Invoice|Shop|Organisation|OrgPaymentServiceProvider|PaymentAccount|Order $parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }
            $table->betweenDates(['date']);

            if ($parent instanceof Fulfilment) {
                foreach ($this->getElementGroups($parent->shop) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }

            } elseif (!($parent instanceof Order || $parent instanceof Invoice)) {
                foreach ($this->getElementGroups($parent) as $key => $elementGroup) {
                    $table->elementGroup(
                        key: $key,
                        label: $elementGroup['label'],
                        elements: $elementGroup['elements']
                    );
                }
            }
            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->defaultSort('-date')
                ->column(key: 'status', label: __('status'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'payment_account_name', label: __('Payment Account'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, searchable: true);
                $table->column(key: 'shop_name', label: __('shop'), canBeHidden: false, searchable: true);
            }
            $table->column(key: 'amount', label: __('amount'), canBeHidden: false, sortable: true, searchable: true, type:'number');
            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true, type:'number');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        return $request->user()->authTo("accounting.{$this->organisation->id}.view");
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgPaymentServiceProvider(Organisation $organisation, OrgPaymentServiceProvider $orgPaymentServiceProvider, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $orgPaymentServiceProvider;
        $this->initialisation($organisation, $request);

        return $this->handle($orgPaymentServiceProvider);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccount(Organisation $organisation, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request);
        return $this->handle($paymentAccount);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccountInOrgPaymentServiceProvider(Organisation $organisation, OrgPaymentServiceProvider $orgPaymentServiceProvider, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $paymentAccount;
        $this->initialisation($organisation, $request);
        return $this->handle($paymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisation($organisation, $request);
        return $this->handle($shop);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
    }

    public function jsonResponse($payments): AnonymousResourceCollection
    {
        return PaymentsResource::collection($payments);
    }


    public function htmlResponse(LengthAwarePaginator $payments, ActionRequest $request): Response
    {
        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();
        $subNavigation   = null;

        if ($this->parent instanceof PaymentAccount) {
            $subNavigation = $this->getPaymentAccountNavigation($this->parent);
        } elseif ($this->parent instanceof Fulfilment) {
            $subNavigation = $this->getSubNavigation($this->parent);
        } elseif ($this->parent instanceof Shop) {
            $subNavigation = $this->getSubNavigationShop($this->parent);
        }

        return Inertia::render(
            'Org/Accounting/Payments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('payments '),
                'pageHead'    => [
                    'subNavigation' => $subNavigation,
                    'icon'      => ['fal', 'fa-coins'],
                    'title'     => __('payments'),
                    'container' => match ($routeName) {
                        'grp.org.accounting.shops.show.payments.index' => [
                            'icon'    => ['fal', 'fa-store-alt'],
                            'tooltip' => __('Shop'),
                            'label'   => Str::possessive($routeParameters['shop']->name)
                        ],
                        default => null
                    },
                ],
                'data'        => PaymentsResource::collection($payments),


            ]
        )->table($this->tableStructure($this->parent));
    }


    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function () use ($routeName, $routeParameters) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => $routeName,
                            'parameters' => $routeParameters
                        ],
                        'label' => __('Payments'),
                        'icon'  => 'fal fa-bars',

                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.org.accounting.shops.show.payments.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.shops.show.dashboard', $routeParameters),
                $headCrumb()
            ),
            'grp.org.accounting.payments.index' =>
            array_merge(
                ShowAccountingDashboard::make()->getBreadcrumbs('grp.org.accounting.dashboard', $routeParameters),
                $headCrumb()
            ),
            'grp.org.accounting.org_payment_service_providers.show.payments.index' =>
            array_merge(
                (new ShowOrgPaymentServiceProvider())->getBreadcrumbs($routeParameters['OrgPaymentServiceProvider']),
                $headCrumb()
            ),
            'grp.org.accounting.org_payment_service_providers.show.payment-accounts.show.payments.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('grp.org.accounting.org_payment_service_providers.show.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),

            'grp.org.accounting.payment-accounts.show.payments.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('grp.org.accounting.payment-accounts.show', $routeParameters),
                $headCrumb()
            ),
            'grp.overview.accounting.payments.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),

            default => []
        };
    }
}
