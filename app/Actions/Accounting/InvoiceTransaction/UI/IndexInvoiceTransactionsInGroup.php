<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Apr 2024 13:42:37 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Accounting\InvoiceTransactionsResource;
use App\InertiaTable\InertiaTable;
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

class IndexInvoiceTransactionsInGroup extends OrgAction
{
    public function handle(Group $group, $prefix = null): LengthAwarePaginator
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

        $queryBuilder->where('invoice_transactions.group_id', $group->id)
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


        $queryBuilder->defaultSort('code');

        return $queryBuilder->allowedSorts(['code', 'name', 'quantity', 'net_amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return IndexInvoiceTransactionsGroupedByAsset::make()->tableStructure($prefix);
    }

    public function htmlResponse(LengthAwarePaginator $transactions, ActionRequest $request): Response
    {
        $title = __('Transactions');
        $icon  = [
            'icon'  => ['fal', 'fa-exchange-alt'],
            'title' => __('Transactions')
        ];

        return Inertia::render(
            'Org/Accounting/InvoiceTransactions',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => $title,
                'pageHead'    => [
                    'title' => $title,
                    'icon'  => $icon,
                ],

                'data' => InvoiceTransactionsResource::collection($transactions),

            ]
        )->table($this->tableStructure($this->group));
    }


    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(group());
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


        return  array_merge(
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
