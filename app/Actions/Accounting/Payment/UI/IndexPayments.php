<?php
/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 16 Mar 2023 08:17:51 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Accounting\Payment\UI;

use App\Actions\Accounting\PaymentAccount\UI\ShowPaymentAccount;
use App\Actions\Accounting\PaymentServiceProvider\ShowPaymentServiceProvider;
use App\Actions\InertiaAction;
use App\Actions\UI\Accounting\AccountingDashboard;
use App\Enums\UI\TabsAbbreviationEnum;
use App\Http\Resources\Accounting\PaymentResource;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccount;
use App\Models\Accounting\PaymentServiceProvider;
use App\Models\Marketing\Shop;
use App\Models\Tenancy\Tenant;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use ProtoneMedia\LaravelQueryBuilderInertiaJs\InertiaTable;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class IndexPayments extends InertiaAction
{
    public function handle($parent): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('payments.reference', '~*', "\y$value\y")
                    ->orWhere('payments.status', '=', $value)
                    ->orWhere('payments.date', '=', $value)
                    ->orWhere('payments.data', '=', $value);
            });
        });

        InertiaTable::updateQueryBuilderParameters(TabsAbbreviationEnum::PAYMENTS->value);

        return QueryBuilder::for(Payment::class)
            ->defaultSort('payments.reference')
            ->select([
                'payments.reference',
                'payments.slug',
                'payments.status',
                'payments.date',
                'payment_accounts.slug as payment_accounts_slug',
                'payment_service_providers.slug as payment_service_providers_slug'
            ])
            ->leftJoin('payment_accounts', 'payments.payment_account_id', 'payment_accounts.id')
            ->leftJoin('payment_service_providers', 'payment_accounts.payment_service_provider_id', 'payment_service_providers.id')
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'PaymentServiceProvider') {
                    $query->where('payment_accounts.payment_service_provider_id', $parent->id);
                } elseif (class_basename($parent) == 'PaymentAccount') {
                    $query->where('payments.payment_account_id', $parent->id);
                } elseif (class_basename($parent) == 'Shop') {
                    $query->where('payments.shop_id', $parent->id);
                } elseif (class_basename($parent) == 'Order') {
                    $query->leftJoin(
                        'paymentables',
                        function ($leftJoin) {
                            $leftJoin->on('paymentables.payment_id', 'payments.id');
                            $leftJoin->on(DB::raw('paymentables.paymentable_type'), DB::raw("'Order'"));
                        }
                    );
                    $query->where('paymentables.paymentable_id', $parent->id);
                }
            })
            ->allowedSorts(['payments.reference', 'payments.status', 'payments.date'])
            ->allowedFilters([$globalSearch])
            ->paginate(
                perPage: $this->perPage ?? config('ui.table.records_per_page'),
                pageName: TabsAbbreviationEnum::PAYMENTS->value.'Page'
            )
            ->withQueryString();
    }

    public function tableStructure(): Closure
    {
        return function (InertiaTable $table) {
            $table
                ->name(TabsAbbreviationEnum::PAYMENTS->value)
                ->pageName(TabsAbbreviationEnum::PAYMENTS->value.'Page')
                ->withGlobalSearch()
                ->defaultSort('reference');

            $table->column(key: 'reference', label: __('reference'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'status', label: __('status'), canBeHidden: false, sortable: true, searchable: true);


            $table->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->can('accounting.edit');

        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('accounting.view')
            );
    }


    public function inTenant(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle(app('currentTenant'));
    }

    public function inPaymentServiceProvider(PaymentServiceProvider $paymentServiceProvider, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($paymentServiceProvider);
    }

    public function inPaymentAccount(PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($paymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccountInShop(Shop $shop, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($paymentAccount);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inPaymentAccountInPaymentServiceProvider(PaymentServiceProvider $paymentServiceProvider, PaymentAccount $paymentAccount, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($paymentAccount);
    }

    public function inShop(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($shop);
    }

    public function jsonResponse($payments): AnonymousResourceCollection
    {
        return PaymentResource::collection($payments);
    }


    public function htmlResponse(LengthAwarePaginator $payments, ActionRequest $request)
    {
        return Inertia::render(
            'Accounting/Payments',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->parameters() == [] ? app('currentTenant') : last($request->route()->parameters())
                ),
                'title'       => __('payments '),
                'pageHead'    => [
                    'title'  => __('payments'),
                    'create' => $this->canEdit
                    && (
                        $this->routeName == 'accounting.payment-accounts.show.payments.index' or
                        $this->routeName == 'accounting.payment-service-providers.show.payment-accounts.show.payments.index'
                    )

                        ? [
                            'route' =>
                                match ($this->routeName) {
                                    'accounting.payment-accounts.show.payments.index' =>
                                    [
                                        'name'       => 'accounting.payment-accounts.show.payments.create',
                                        'parameters' => array_values($this->originalParameters)
                                    ],
                                    'accounting.payment-service-providers.show.payment-accounts.show.payments.index' =>
                                    [
                                        'name'       => 'accounting.payment-service-providers.show.payment-accounts.show.payments.create',
                                        'parameters' => array_values($this->originalParameters)
                                    ]
                                }


                            ,
                            'label' => __('payments')
                        ] : false,
                ],
                'data'        => PaymentResource::collection($payments),


            ]
        )->table($this->tableStructure());
    }


    public function getBreadcrumbs(string $routeName, Shop|Tenant|PaymentServiceProvider|PaymentAccount $parent): array
    {
        $headCrumb = function (array $routeParameters = []) use ($routeName) {
            return [
                 [
                     'type'   => 'simple',
                     'simple' => [
                         'route' => [
                             'name'       => $routeName,
                             'parameters' => $routeParameters
                         ],
                         'label' => __('payments'),
                         'icon'  => 'fal fa-bars',

                     ],
                ],
            ];
        };

        return match ($routeName) {
            'accounting.payments.index' =>
            array_merge(
                (new AccountingDashboard())->getBreadcrumbs(),
                $headCrumb()
            ),
            'accounting.payment-service-providers.show.payments.index' =>
            array_merge(
                (new ShowPaymentServiceProvider())->getBreadcrumbs($parent),
                $headCrumb([$parent->slug])
            ),
            'accounting.payment-service-providers.show.payment-accounts.show.payments.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('accounting.payment-service-providers.show.payment-accounts.show', $parent),
                $headCrumb([$parent->paymentServiceProvider->slug, $parent->slug])
            ),

            'accounting.payment-accounts.show.payments.index' =>
            array_merge(
                (new ShowPaymentAccount())->getBreadcrumbs('accounting.payment-accounts.show', $parent),
                $headCrumb([$parent->slug])
            ),

            default => []
        };
    }
}
