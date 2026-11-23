<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-11h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Models\Inventory\Warehouse;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

trait WithMobileDateRange
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, array<int, string>>
     */
    protected function dateRangeRules(array $query): array
    {
        return [
            'from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'to'   => ['sometimes', 'nullable', 'date_format:Y-m-d', ...(filled($query['from'] ?? null) ? ['after_or_equal:from'] : [])],
        ];
    }

    protected function whereWithinDays(Builder|QueryBuilder $query, string $column, Warehouse $warehouse, ?string $from, ?string $to): void
    {
        $timezone = $warehouse->organisation->timezone?->name ?? config('app.timezone');

        if (filled($from)) {
            $query->where($column, '>=', Carbon::createFromFormat('!Y-m-d', $from, $timezone)->utc()->toDateTimeString());
        }

        if (filled($to)) {
            $query->where($column, '<', Carbon::createFromFormat('!Y-m-d', $to, $timezone)->addDay()->utc()->toDateTimeString());
        }
    }
}
