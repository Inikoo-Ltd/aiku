<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\InertiaTable\InertiaTable;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Guest;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

class IndexJobPositionGuests
{
    use AsAction;

    public function handle(JobPosition $jobPosition, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('guests.contact_name', $value)
                    ->orWhereStartWith('guests.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Guest::class);
        $queryBuilder->join('users', 'users.id', '=', 'guests.user_id');
        $queryBuilder->join('user_has_pseudo_job_positions', 'user_has_pseudo_job_positions.user_id', '=', 'users.id');
        $queryBuilder->where('user_has_pseudo_job_positions.job_position_id', $jobPosition->id);

        return $queryBuilder
            ->select([
                'guests.id',
                'guests.slug',
                'guests.code',
                'guests.contact_name',
                'guests.email',
                'guests.status',
                'user_has_pseudo_job_positions.share',
            ])
            ->defaultSort('guests.code')
            ->allowedSorts(['code', 'contact_name', 'email', 'share'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public static function numberGuests(JobPosition $jobPosition): int
    {
        return DB::table('user_has_pseudo_job_positions')
            ->join('users', 'users.id', '=', 'user_has_pseudo_job_positions.user_id')
            ->join('guests', 'guests.user_id', '=', 'users.id')
            ->where('user_has_pseudo_job_positions.job_position_id', $jobPosition->id)
            ->whereNull('guests.deleted_at')
            ->count('guests.id');
    }

    public function tableStructure(JobPosition $jobPosition, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($jobPosition, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table->withLabelRecord([__('guest'), __('guests')]);
            $table->withEmptyState(
                [
                    'title' => __('No guests hold this responsibility'),
                    'count' => self::numberGuests($jobPosition),
                ]
            );

            $table
                ->withGlobalSearch()
                ->column(key: 'status', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'contact_name', label: __('Name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'email', label: __('Email'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'share', label: __('Share'), canBeHidden: false, sortable: true)
                ->defaultSort('code');
        };
    }
}
