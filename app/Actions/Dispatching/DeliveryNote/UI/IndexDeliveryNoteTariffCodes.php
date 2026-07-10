<?php

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Actions\Dispatching\DeliveryNote\UI\Traits\WithDeliveryNoteTariffCodesQuery;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;

class IndexDeliveryNoteTariffCodes extends OrgAction
{
    use WithDeliveryNoteTariffCodesQuery;

    public function handle(DeliveryNote $deliveryNote, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('tariff_code', 'ilike', "$value%")
                    ->orWhere('parts', 'ilike', "%$value%")
                    ->orWhere('origin', 'ilike', "$value%");
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $base = $this->getTariffCodesBaseQuery($deliveryNote);

        return QueryBuilder::for(DeliveryNoteItem::query()->fromSub($base, 'tc'))
            ->defaultSort('tariff_code')
            ->allowedSorts(['tariff_code', 'origin', 'num_parts', 'units', 'weight', 'amount'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure(DeliveryNote $deliveryNote, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withGlobalSearch();
            $table->withEmptyState([
                'icons' => ['fal fa-globe'],
                'title' => __('No tariff codes found'),
            ]);

            $table->column(key: 'tariff_code', label: __('Tariff code'), canBeHidden: false, sortable: true, searchable: true);
            $table->column(key: 'description', label: __('Description'), canBeHidden: true);
            $table->column(key: 'origin', label: __('Origin'), sortable: true);
            $table->column(key: 'dg', label: __('DG'), align: 'center');
            $table->column(key: 'parts', label: __('Parts'), canBeHidden: false);
            $table->column(key: 'un_numbers', label: __('UN numbers'), canBeHidden: true);
            $table->column(key: 'units', label: __('Units'), sortable: true, align: 'right');
            $table->column(key: 'weight', label: __('Weight'), sortable: true, align: 'right');
            $table->column(key: 'amount', label: __('Amount'), sortable: true, align: 'right');

            $table->defaultSort('tariff_code');
        };
    }
}
