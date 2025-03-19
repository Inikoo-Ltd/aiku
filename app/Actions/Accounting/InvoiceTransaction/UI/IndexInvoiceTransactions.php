<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoiceTransactions extends OrgAction
{
    protected Group|Invoice $parent;

    public function handle(Group|Invoice $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('invoice_transactions.number', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(InvoiceTransaction::class);
        $queryBuilder->leftJoin('historic_assets', 'invoice_transactions.historic_asset_id', 'historic_assets.id');
        $queryBuilder->leftJoin('assets', 'invoice_transactions.asset_id', 'assets.id');

        $queryBuilder->select(
            [
                'invoice_transactions.invoice_id',
                'invoice_transactions.model_type',
                'invoice_transactions.in_process',
                'invoice_transactions.data',
                'historic_assets.code',
                'historic_assets.name',
                'invoice_transactions.historic_asset_id',
                'assets.id',
                'assets.shop_id',
                DB::raw('SUM(invoice_transactions.quantity) as quantity'),
                DB::raw('SUM(invoice_transactions.net_amount) as net_amount'),
            ]
        );

        if ($parent instanceof Group) {
            $queryBuilder->where('invoice_transactions.group_id', $parent->id)
            ->leftJoin('invoices', 'invoice_transactions.invoice_id', 'invoices.id')
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->addSelect("currencies.code AS currency_code")
            ->groupBy(
                'historic_assets.code',
                'invoice_transactions.invoice_id',
                'invoice_transactions.data',
                'historic_assets.name',
                'assets.id',
                'invoice_transactions.in_process',
                'currencies.code',
                'invoice_transactions.historic_asset_id',
                'assets.shop_id',
                'invoice_transactions.model_type'
            );
        } else {
            $queryBuilder->where('invoice_transactions.invoice_id', $parent->id)
            ->addSelect(
                DB::raw("'{$parent->currency->code}' AS currency_code"),
            )
            ->groupBy(
                'historic_assets.code',
                'invoice_transactions.invoice_id',
                'invoice_transactions.data',
                'historic_assets.name',
                'assets.id',
                'invoice_transactions.in_process',
                'invoice_transactions.historic_asset_id',
                'assets.shop_id',
                'invoice_transactions.model_type'
            );
        }

        $queryBuilder->defaultSort('code');

        return $queryBuilder->allowedSorts(['code', 'name', 'quantity', 'net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group|Invoice $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $parent) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }


            $table
                ->withModelOperations()
                ->withGlobalSearch();

            $table->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true);

            $table->column(key: 'name', label: __('description'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'quantity', label: __('quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('net'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            if (!app()->environment('production')) {
                if ($parent instanceof Invoice && $parent->type === InvoiceTypeEnum::REFUND && $parent->in_process) {
                    $table->column(key: 'action', label: __('action'), canBeHidden: false, sortable: false, searchable: false);
                }
            }
            $table->defaultSort('code');
        };
    }

    public function htmlResponse(LengthAwarePaginator $transactions, ActionRequest $request): Response
    {
        $title      = __('Invoice Transactions');
        $icon       = [
            'icon'  => ['fal', 'fa-exchange-alt'],
            'title' => __('Invoice Transactions')
        ];

        if ($this->parent instanceof Group) {
            $title = __('Transactions');
            $icon = [
                'icon'  => ['fal', 'fa-exchange-alt'],
                'title' => __('Transactions')
            ];
        }

        return Inertia::render(
            'Org/Accounting/InvoiceTransactions',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title'      => $title,
                    'icon'       => $icon,
                ],

                'data' => InvoiceTransactionsResource::collection($transactions),

            ]
        )->table($this->tableStructure($this->group));
    }


    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->sales = false;
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle($this->parent);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Transactions'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };


        return match ($routeName) {
            'grp.overview.ordering.transactions.index' =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ]
                )
            ),
            default => []
        };
    }

}
