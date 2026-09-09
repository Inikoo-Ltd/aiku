<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\ApiToken\UI;

use App\InertiaTable\InertiaTable;
use App\Models\CRM\Customer;
use App\Models\CRM\RetinaApiRequest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaApiRequests
{
    use AsAction;

    public function handle(Customer $customer, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereRaw('retina_api_requests.path ILIKE ?', ["%$value%"])
                    ->orWhereRaw('retina_api_requests.message ILIKE ?', ["%$value%"]);
            });
        });

        return QueryBuilder::for(RetinaApiRequest::class)
            ->where('customer_id', $customer->id)
            ->defaultSort('-created_at')
            ->allowedSorts(['created_at', 'status', 'duration_ms', 'path'])
            ->allowedFilters([$globalSearch])
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
                ->withEmptyState([
                    'title' => __('No API calls recorded yet'),
                    'count' => 0,
                ])
                ->column(key: 'created_at', label: __('When'), canBeHidden: false, sortable: true, type: 'date_hms')
                ->column(key: 'method', label: __('Method'), canBeHidden: false)
                ->column(key: 'path', label: __('Endpoint'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Result'), canBeHidden: false, sortable: true)
                ->column(key: 'message', label: __('Message'))
                ->column(key: 'duration_ms', label: __('Duration'), sortable: true)
                ->defaultSort('-created_at');
        };
    }
}
