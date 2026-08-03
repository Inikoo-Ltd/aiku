<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\UI;

use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

trait WithBucketNavigation
{
    /**
     * Walks the list the model was opened from, one row at a time, matching the index bucket filters and sort.
     *
     * @param  array<string, string|array<string>>  $sortColumns  request sort key => qualified column(s)
     * @param  array{0: string|array<string>, 1: bool}  $defaultSort  qualified column(s) and whether they are descending
     * @param  array<string, mixed>  $sortValues  the model's value for columns it does not carry as an attribute
     */
    protected function getBucketNeighbour(
        Builder $query,
        Model $model,
        ?string $sort,
        array $sortColumns,
        array $defaultSort,
        bool $forward,
        array $sortValues = []
    ): ?Model {
        [$columns, $isDescending] = $this->resolveBucketSort($sort, $sortColumns, $defaultSort);

        $values = $this->getBucketSortValues($model, $columns, $sortValues);
        $key    = $model->getTable().'.'.$model->getKeyName();

        if (in_array(null, $values, true)) {
            [$columns, $isDescending] = [Arr::wrap($defaultSort[0]), $defaultSort[1]];
            $values                   = $this->getBucketSortValues($model, $columns, $sortValues);
        }

        if (in_array(null, $values, true)) {
            $columns = [];
            $values  = [];
        }

        $walkingDown = $forward == $isDescending;
        $operator    = $walkingDown ? '<' : '>';
        $direction   = $walkingDown ? 'desc' : 'asc';
        $cursor    = implode(', ', [...$columns, $key]);
        $bindings  = [...$values, $model->getKey()];
        $questions = implode(', ', array_fill(0, count($bindings), '?'));

        $query->select($model->getTable().'.*')
            ->whereRaw("($cursor) $operator ($questions)", $bindings);

        foreach ($columns as $column) {
            $query->orderByRaw("$column $direction");
        }

        return $query->orderBy($key, $direction)->first();
    }

    /**
     * @param  array<string, string|array<string>>  $sortColumns
     * @param  array{0: string|array<string>, 1: bool}  $defaultSort
     * @return array{0: array<string>, 1: bool}
     */
    private function resolveBucketSort(?string $sort, array $sortColumns, array $defaultSort): array
    {
        $columns = Arr::get($sortColumns, ltrim((string)$sort, '-'));

        if (!$columns) {
            return [Arr::wrap($defaultSort[0]), $defaultSort[1]];
        }

        return [Arr::wrap($columns), str_starts_with((string)$sort, '-')];
    }

    /**
     * @param  array<string>  $columns
     * @param  array<string, mixed>  $sortValues
     * @return array<mixed>
     */
    private function getBucketSortValues(Model $model, array $columns, array $sortValues): array
    {
        return array_map(function (string $column) use ($model, $sortValues) {
            if (array_key_exists($column, $sortValues)) {
                return $sortValues[$column];
            }

            $value = $model->getAttribute(Str::afterLast($column, '.'));

            return $value instanceof BackedEnum ? $value->value : $value;
        }, $columns);
    }
}
