<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Oct 2024 00:23:21 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\History\UI;

use App\Actions\SysAdmin\User\Traits\WithFormattedUserHistories;
use App\Enums\Helpers\Audit\AuditEventEnum;
use App\InertiaTable\InertiaTable;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Models\Audit;
use Spatie\QueryBuilder\AllowedFilter;

class IndexHistory
{
    use AsAction;
    use WithAttributes;
    use WithFormattedUserHistories;

    public string $model;

    public function handle($model, $prefix = null, $eventScopeFilter = null): LengthAwarePaginator|array|bool
    {
        $this->model = class_basename($model);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('user_type', $value)
                    ->orWhereWith('user_type', $value)
                    ->orWhereWith('url', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Audit::class);

        $queryBuilder->orderBy('id', 'DESC');
        $queryBuilder->where('auditable_type', $this->model);
        $queryBuilder->where('event', '!=', AuditEventEnum::CUSTOMER_NOTE->value);

        if($eventScopeFilter) {
            $queryBuilder->where('event', $eventScopeFilter);
        }

        if (isset($model->id)) {
            $queryBuilder->where('auditable_id', $model->id);
        }

        return $queryBuilder
            ->defaultSort('audits.created_at')
            ->allowedSorts(['ip_address','auditable_id', 'auditable_type', 'user_type', 'url','created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null, ?array $exportLinks = null): Closure
    {
        return function (InertiaTable $table) use ($exportLinks, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withExportLinks($exportLinks)
                ->column(key: 'expand', label: '', type: 'icon')
                ->column(key: 'datetime', label: __('Date'), canBeHidden: false, sortable: true)
                ->column(key: 'user_name', label: __('User'), canBeHidden: false, sortable: true)
                ->column(key: 'values', label: __('Value'), canBeHidden: false, sortable: true)
                ->column(key: 'event', label: __('Action'), canBeHidden: false, sortable: true)
                ->defaultSort('ip_address');
        };
    }
}
