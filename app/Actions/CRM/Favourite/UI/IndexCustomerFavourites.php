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
    private Customer $parent;

    public function handle(Customer $parent, $prefix = null): LengthAwarePaginator
    {
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
        $query->whereNull('favourites.unfavourited_at');

        $query->leftJoin('products', 'favourites.product_id', '=', 'products.id');
        $query->leftJoin('webpages', function ($join) {
            $join->on('products.id', '=', 'webpages.model_id')
                ->where('webpages.model_type', '=', 'Product');
        });

        return $query->defaultSort('products.code')
            ->select([
                'favourites.id as favourite_id',
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.description',
                'products.price',
                'products.image_id',
                'webpages.url as webpage_url',
                'webpages.id as webpage_id',
            ])
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


            $table->column(key: 'code', label: __('code'), canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
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
