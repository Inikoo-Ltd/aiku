<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\InvoicesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Asset;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexInvoicesInProduct extends OrgAction
{
    public function handle(MasterAsset|Asset $parent, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('invoices.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Invoice::class);
        $query->where('invoices.in_process', false);
        $query->leftJoin('currencies', 'invoices.currency_id', 'currencies.id');
        $query->join('invoice_transactions', 'invoices.id', 'invoice_transactions.invoice_id');

        if ($parent instanceof MasterAsset) {
            $query->where('invoice_transactions.master_asset_id', $parent->id);
        } elseif ($parent instanceof Asset) {
            $query->where('invoice_transactions.asset_id', $parent->id);
        }

        $query->distinct();

        return $query
            ->select([
                'invoices.reference',
                'invoices.customer_name',
                'invoices.date',
                'invoices.pay_status',
                'invoices.net_amount',
                'invoices.total_amount',
                'currencies.code as currency_code',
                'currencies.symbol as currency_symbol',
            ])
            ->defaultSort('-date')
            ->allowedSorts(['reference', 'customer_name', 'date', 'pay_status', 'net_amount', 'total_amount'])
            ->allowedFilters([$globalSearch])
            ->withBetweenDates(['date'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->betweenDates(['date'])
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'customer_name', label: __('Customer'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right')
                ->column(key: 'pay_status', label: __('Payment'), canBeHidden: false, sortable: true, searchable: true, type: 'icon')
                ->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->column(key: 'total_amount', label: __('Total'), canBeHidden: false, sortable: true, searchable: true, type: 'number')
                ->defaultSort('-date');
        };
    }

    public function asController(Organisation $organisation, Shop $shop, Product $product, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($product->asset);
    }

    public function inMaster(MasterShop $masterShop, MasterAsset $masterProduct, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($masterShop->group, $request);

        return $this->handle($masterProduct);
    }

    public function htmlResponse(LengthAwarePaginator $invoices): Response
    {
        return Inertia::render(
            'InvoicesInProduct',
            [
                'title'    => __('Invoices'),
                'pageHead' => [
                    'title' => __('Invoices'),
                ],
                'data'     => InvoicesResource::collection($invoices),
            ]
        )->table($this->tableStructure());
    }
}
