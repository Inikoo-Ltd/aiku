<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Helpers\Media;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexBasketTransactions extends OrgAction
{
    public function handle(Order $order, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('assets.code', $value)
                    ->orWhereStartWith('assets.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(Transaction::class);

        $query->where('transactions.order_id', $order->id);
        $query->whereIn('transactions.model_type', ['Product', 'Service']);

        $query->leftjoin('assets', 'transactions.asset_id', '=', 'assets.id');
        $query->leftjoin('products', 'assets.model_id', '=', 'products.id');
        $query->leftjoin('upcoming_transactions', 'transactions.id', '=', 'upcoming_transactions.transaction_id');
        $query->leftJoin('webpages', function ($join) {
            $join->on('webpages.id', '=', 'products.webpage_id')->whereNull('webpages.deleted_at');
        });

        $transactions = $query->defaultSort('transactions.id')
            ->select([
                'transactions.id',
                'transactions.state',
                'transactions.status',
                'transactions.quantity_ordered',
                'transactions.quantity_bonus',
                'transactions.quantity_dispatched',
                'transactions.quantity_fail',
                'transactions.quantity_cancelled',
                'transactions.is_cut_view',
                'transactions.gross_amount',
                'transactions.net_amount',
                'transactions.model_type as model_type',
                'transactions.created_at',
                'assets.code as asset_code',
                'assets.name as asset_name',
                'assets.type as asset_type',
                'products.id as product_id',
                'products.price as price',
                'products.units as units',
                'products.slug as product_slug',
                'products.image_id as product_image_id',
                'products.available_quantity as available_quantity',
                'transactions.offers_data',

                'upcoming_transactions.public_notes as upcoming_transaction_public_notes',
                'upcoming_transactions.private_notes as upcoming_transaction_private_notes',
                'upcoming_transactions.type as upcoming_transaction_type',

                'webpages.id as webpage_id',
                'webpages.canonical_url as webpage_canonical_url',
                'webpages.website_id as webpage_website_id',
                'webpages.group_id as webpage_group_id',
                'webpages.organisation_id as webpage_organisation_id',
                'webpages.shop_id as webpage_shop_id',
            ])
            ->selectRaw("'{$order->currency->code}'  as currency_code")
            ->selectRaw("'{$order->shop->type->value}'  as shop_type")
            ->allowedSorts(['asset_code', 'asset_name', 'net_amount', 'quantity_ordered', 'price'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $images = Media::whereIn('id', $transactions->getCollection()->pluck('product_image_id')->filter()->unique())->get()->keyBy('id');
        $transactions->getCollection()->each(function (Transaction $transaction) use ($images) {
            $transaction->setRelation('productImage', $images->get($transaction->product_image_id));
        });

        return $transactions;
    }

    public function tableStructure($tableRows = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix, $tableRows) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withFooterRows($tableRows);
            $table
                ->withEmptyState(
                    [
                        'title' => __("No transactions found"),
                    ]
                );

            $table->column(key: 'image', label: '', canBeHidden: false, sortable: false, searchable: false);
            $table->column(key: 'asset_name', label: __('Product Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'price', label: __('Price'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'quantity_ordered', label: __('Quantity'), canBeHidden: false, sortable: true, searchable: true, type: 'number');
            $table->column(key: 'net_amount', label: __('Net'), canBeHidden: false, sortable: true, searchable: true, type: 'currency');
            $table->column(key: 'actions', label: __('Action'), canBeHidden: false, sortable: false, searchable: false);
        };
    }


}
