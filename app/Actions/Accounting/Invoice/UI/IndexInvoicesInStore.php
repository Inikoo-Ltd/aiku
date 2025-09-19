<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 16 Apr 2025 13:48:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\Traits\Authorisations\Inventory\WithGroupOverviewAuthorisation;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\OrgPaymentServiceProvider;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Illuminate\Support\Facades\DB;


class IndexInvoicesInStore extends OrgAction
{
    use WithGroupOverviewAuthorisation;

    public function handle(Group $group, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $invoiceCounts = DB::table('invoices')
            ->select('customer_id', DB::raw('count(*) as invoice_count'))
            ->where('group_id', $group->id)
            ->where('type', InvoiceTypeEnum::INVOICE)
            ->groupBy('customer_id');

        $refundCounts = DB::table('invoices')
            ->select('customer_id', DB::raw('count(*) as refund_count'))
            ->where('group_id', $group->id)
            ->where('type', InvoiceTypeEnum::REFUND)
            ->groupBy('customer_id');

        $queryBuilder = QueryBuilder::for(Invoice::class)
            ->where('invoices.type', InvoiceTypeEnum::INVOICE)
            ->whereNot('invoices.in_process', true)
            ->where('invoices.group_id', $group->id)
            ->leftjoin('customers', 'invoices.customer_id', '=', 'customers.id')
            ->leftjoin('organisations', 'invoices.organisation_id', '=', 'organisations.id')
            ->leftjoin('shops', 'invoices.shop_id', '=', 'shops.id')
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->leftJoin('invoice_stats', 'invoices.id', 'invoice_stats.invoice_id')
            ->leftJoinSub($invoiceCounts, 'invoice_counts', function ($join) {
                $join->on('invoices.customer_id', '=', 'invoice_counts.customer_id');
            })
            ->leftJoinSub($refundCounts, 'refund_counts', function ($join) {
                $join->on('invoices.customer_id', '=', 'refund_counts.customer_id');
            })
            ->defaultSort('-invoices.created_at')
            ->select([
                'invoices.id',
                'invoices.total_amount',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.in_process',
                'invoices.slug',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                DB::raw('COALESCE(invoice_counts.invoice_count, 0) as number_of_invoices'),
                DB::raw('COALESCE(refund_counts.refund_count, 0) as number_of_refunds'),
            ]);

        return $queryBuilder
            ->allowedSorts(['number', 'total_amount', 'customer_name', 'number_of_invoices', 'number_of_refunds'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $group) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table->betweenDates(['date']);

            $stats     = $group->orderingStats;
            $noResults = __("This group hasn't been invoiced");

            $table->withGlobalSearch();
            if (!($group instanceof OrgPaymentServiceProvider)) {
                $table->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_invoices ?? 0,
                    ]
                );
            }
            $table->column(key: 'shop_code', label: __('code'), canBeHidden: false, searchable: true);
            $table->column(key: 'customer_name', label: __('customer name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'number_of_invoices', label: __('Invoices'), canBeHidden: false, sortable: true);
            $table->column(key: 'number_of_refunds', label: __('Refunds'), canBeHidden: false, sortable: true);
            $table->column(key: 'total_amount', label: __('total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-invoices.created_at');
        };
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        $subNavigation = [];
        $title         = __('Shop Invoices');

        $icon = [
            'icon'  => ['fal', 'fa-store'],
            'title' => __('invoices')
        ];

        $afterTitle = null;
        $iconRight  = null;
        $model      = null;
        $actions    = [];

        $routeName       = $request->route()->getName();
        $routeParameters = $request->route()->originalParameters();

        $inertiaRender = Inertia::render(
            'Org/Accounting/InvoicesStore',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $routeName,
                    $routeParameters
                ),
                'title'       => __('invoices'),
                'pageHead'    => [
                    'title'         => $title,
                    'model'         => $model,
                    'afterTitle'    => $afterTitle,
                    'iconRight'     => $iconRight,
                    'icon'          => $icon,
                    'subNavigation' => $subNavigation,
                    'actions'       => $actions,
                ],
                'data' => $invoices,
            ]
        );
        return $inertiaRender->table($this->tableStructure(group: $this->group));
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);
        return $this->handle(group());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = [], ?string $suffix = null) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Invoices'),
                        'icon'  => 'fal fa-bars',
                    ],
                    'suffix' => $suffix
                ]
            ];
        };
        return array_merge(
            ShowGroupOverviewHub::make()->getBreadcrumbs(),
            $headCrumb(
                [
                    'name'       => $routeName,
                    'parameters' => $routeParameters
                ]
            )
        );
    }
}
