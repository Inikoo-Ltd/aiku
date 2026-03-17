<?php

namespace App\Actions\Discounts\OfferCampaign\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Http\Resources\Accounting\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesInOfferTotal extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value)
                    ->orWhereWith('invoices.customer_name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Invoice::class);
        $queryBuilder->where('invoices.type', InvoiceTypeEnum::INVOICE);
        $queryBuilder->where('invoices.in_process', false);
        $queryBuilder->where('invoices.shop_id', $parent->id);

        $queryBuilder->whereExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('transaction_has_offer_allowances')
                ->join('invoice_transactions', 'invoice_transactions.transaction_id', '=', 'transaction_has_offer_allowances.transaction_id')
                ->whereColumn('invoice_transactions.invoice_id', 'invoices.id');
        });

        $queryBuilder->leftJoin('organisations', 'invoices.organisation_id', '=', 'organisations.id');
        $queryBuilder->leftJoin('shops', 'invoices.shop_id', '=', 'shops.id');
        $queryBuilder->leftJoin('customers', 'invoices.customer_id', '=', 'customers.id');

        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'invoices.id',
                'invoices.reference',
                'invoices.total_amount',
                'invoices.net_amount',
                'invoices.pay_status',
                'invoices.date',
                'invoices.type',
                'invoices.created_at',
                'invoices.updated_at',
                'invoices.in_process',
                'invoices.slug',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'shops.code as shop_code',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'customers.company_name as customer_company',
            ])
            ->leftJoin('currencies', 'invoices.currency_id', 'currencies.id')
            ->allowedSorts(['pay_status', 'total_amount', 'net_amount', 'date', 'customer_name', 'reference'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->betweenDates(['date']);

            $table
                ->withModelOperations($modelOperations)
                ->withLabelRecord([__('invoice'), __('invoices')])
                ->withGlobalSearch()
                ->withEmptyState([
                    'title' => __('No invoices found'),
                ])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'total_amount', label: __('Total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function htmlResponse(LengthAwarePaginator $invoices, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Accounting/Invoices',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->getName(), $request->route()->originalParameters()),
                'title'       => __('Invoices'),
                'pageHead'    => [
                    'title'      => __('Invoices'),
                    'model'      => __('Offer Campaign'),
                    'afterTitle' => [
                        'label' => __('Invoices'),
                    ],
                    'iconRight'  => [
                        'icon' => 'fal fa-file-invoice-dollar',
                    ],
                    'icon'       => [
                        'icon'  => ['fal', 'fa-file-invoice-dollar'],
                        'title' => __('Invoices'),
                    ],
                ],
                'data' => InvoicesResource::collection($invoices),
            ]
        )->table($this->tableStructure());
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Invoices'),
                        'icon'  => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            default => []
        };
    }
}
