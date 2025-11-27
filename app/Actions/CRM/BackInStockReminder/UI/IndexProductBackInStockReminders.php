<?php

/*
 * author Arya Permana - Kirin
 * created on 16-10-2024-11h-03m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\BackInStockReminder\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\ProductBackInStockRemindersResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\CRM\BackInStockReminder;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProductBackInStockReminders extends OrgAction
{
    private Product $parent;

    public function handle(Product $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereStartWith('customers.reference', $value)
                    ->orWhereAnyWordStartWith('customers.name', 'ILIKE', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }


        $query = QueryBuilder::for(BackInStockReminder::class);

        $query->where('back_in_stock_reminders.product_id', $parent->id);

        $query->leftJoin('customers', 'back_in_stock_reminders.customer_id', '=', 'customers.id');

        return $query->defaultSort('customers.reference')
            ->select([
                'customers.id',
                'customers.slug',
                'customers.reference',
                'customers.name',
                'customers.contact_name',
                'customers.email',
                'customers.phone',
            ])
            ->allowedSorts(['reference', 'contact_name'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Product $parent, $prefix = null): Closure
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
            $noResults = __("Nobody need reminder for this product");


            $table
                ->withGlobalSearch()
                ->withEmptyState(
                    [
                        'title' => $noResults,
                    ]
                );


            $table->column(key: 'reference', label: __('Reference'), canBeHidden: false, searchable: true);
            $table->column(key: 'contact_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: false, searchable: true);
            $table->column(key: 'phone', label: __('Phone'), canBeHidden: false, sortable: false, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {

        $this->canEdit = $request->user()->authTo("products.{$this->shop->id}.edit");

        return $request->user()->authTo("products.{$this->shop->id}.view");
    }

    public function jsonResponse(LengthAwarePaginator $reminder): AnonymousResourceCollection
    {
        return ProductBackInStockRemindersResource::collection($reminder);
    }

}
