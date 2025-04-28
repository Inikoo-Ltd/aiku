<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-10h-54m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\BackInStockReminder\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCRMAuthorisation;
use App\Http\Resources\CRM\CustomerBackInStockRemindersResource;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\Customer;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;

class IndexCustomerBackInStockReminders extends OrgAction
{
    use WithCRMAuthorisation;

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


        $query = QueryBuilder::for(BackInStockReminder::class);

        $query->where('back_in_stock_reminders.customer_id', $parent->id);

        $query->leftJoin('products', 'back_in_stock_reminders.product_id', '=', 'products.id');

        return $query->defaultSort('products.code')
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.description',
                'products.price',
                'products.image_id',
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


            $noResults = __("Customer has no reminders");
            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                        'count' => $parent->stats->number_reminders ?? 0
                    ]
                );


            $table->column(key: 'code', label: __('code'), canBeHidden: false, searchable: true);
            $table->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true);
        };
    }


    public function jsonResponse(LengthAwarePaginator $reminders): AnonymousResourceCollection
    {
        return CustomerBackInStockRemindersResource::collection($reminders);
    }

}
