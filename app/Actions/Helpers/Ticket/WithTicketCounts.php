<?php

/*
 * Author Louis Perez
 * Created on 04-11-2026-13h-18m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Helpers\Ticket;

use App\Models\Helpers\Ticket;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

trait WithTicketCounts
{
    /**
     * Every count comes from one query, as count(*) filter (where …). Columns the base already
     * selects come back alongside the counts under their own names.
     *
     * @param  array<string, Closure(Builder): Builder>  $conditions
     *
     * @return array<string, mixed>
     */
    protected function countEach(Builder $base, array $conditions): array
    {
        $query       = (clone $base)->toBase();
        $countColumn = fn (int $index): string => "count_$index";

        foreach (array_values($conditions) as $index => $condition) {
            $where = $condition(Ticket::query()->withoutGlobalScopes())->toBase();
            $query->selectRaw('count(*) filter ('.$query->getGrammar()->compileWheres($where).') as '.$countColumn($index), $where->getBindings());
        }

        $row = (array) $query->first();

        return [
            ...Arr::except($row, array_map($countColumn, array_keys(array_values($conditions)))),
            ...collect(array_keys($conditions))->mapWithKeys(fn (string $key, int $index) => [$key => (int) $row[$countColumn($index)]])->all(),
        ];
    }

    /**
     * @param  array<int, BackedEnum>  $cases
     *
     * @return array<string, Closure(Builder): Builder>
     */
    protected function conditionPerCase(string $column, array $cases): array
    {
        return collect($cases)->mapWithKeys(fn (BackedEnum $case) => [
            "$column.$case->value" => fn (Builder $query) => $query->where($column, $case),
        ])->all();
    }
}
