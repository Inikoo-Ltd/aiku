<?php

/*
 * author Arya Permana - Kirin
 * created on 14-10-2024-10h-02m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\Favourite\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\CustomerFavouritesResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerFavourites extends OrgAction
{
    public function handle(Customer $parent, $prefix = null): LengthAwarePaginator
    {
        $basket = $parent->orderInBasket;
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('products.code', $value)
                    ->orWhereAnyWordStartWith('products.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(Favourite::class);
        $query->where('favourites.customer_id', $parent->id);
        $query->leftJoin('products', 'favourites.product_id', '=', 'products.id');
        $select = [];
        if ($basket) {
            $query->leftJoin('transactions', function ($join) use ($basket) {
                $join->on('products.id', '=', 'transactions.model_id')
                    ->where('transactions.model_type', '=', 'Product')
                    ->where('transactions.order_id', '=', $basket->id)
                    ->whereNull('transactions.deleted_at');
            });
            $select[] = 'transactions.id as transaction_id';
            $select[] = 'transactions.quantity_ordered as quantity_ordered';
        }

        $query->leftJoin('webpages', function ($join) {
            $join->on('products.id', '=', 'webpages.model_id')
                ->where('webpages.model_type', '=', 'Product');
        });

        $query->whereNull('favourites.unfavourited_at');
        $select = array_merge($select, [
                'products.id',
                'products.image_id',
                'products.code',
                'products.group_id',
                'products.organisation_id',
                'products.shop_id',
                'products.name',
                'products.available_quantity',
                'products.price',
                'products.rrp',
                'products.state',
                'products.status',
                'products.created_at',
                'products.updated_at',
                'products.units',
                'products.unit',
                'products.top_seller',
                'products.web_images',
                'products.slug',
                'webpages.canonical_url',
        ]);

        return $query->defaultSort('products.code')
            ->select($select)
            ->allowedSorts(['code', 'name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Customer $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            if ($prefix) {
                InertiaTable::updateQueryBuilderParameters($prefix);
            }


            $stats     = $parent->stats;
            $noResults = __("Customer has no favourites");


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $stats->number_favourites ?? 0
                    ]
                );


            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true, );
            $table->column(key: 'name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'actions', label: '', canBeHidden: false);
        };
    }

    public function authorize(ActionRequest $request): bool
    {

        $this->canEdit = $request->user()->authTo("crm.{$this->shop->id}.view");

        return $request->user()->authTo("crm.{$this->shop->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $favourites): AnonymousResourceCollection
    {
        return CustomerFavouritesResource::collection($favourites);
    }

}
