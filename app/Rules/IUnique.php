<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 30 Sep 2023 17:34:09 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class IUnique implements ValidationRule
{
    protected string $table;

    protected ?string $column;

    public array $extraConditions;

    protected ?string $message = null;

    public function __construct($table, $column = null, array $extraConditions = [], ?string $message = null)
    {
        $this->table = $table;

        $this->column = $column;

        $this->extraConditions = $extraConditions;

        $this->message = $message;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->column === null) {
            $this->column = $attribute;
        }

        $count = DB::table($this->table)
            ->whereRaw("lower($this->column) = lower(?)", [$value]);

        if (! blank($this->extraConditions)) {
            foreach ($this->extraConditions as $columnCollection) {
                if (! isset($columnCollection['column'])) {
                    continue;
                }

                if (empty($columnCollection['operator'])) {
                    $columnCollection['operator'] = '=';
                }

                if ($columnCollection['operator'] === 'null') {
                    $count = $count->whereNull($columnCollection['column']);

                    continue;
                }

                if ($columnCollection['operator'] === 'notNull') {
                    $count = $count->whereNotNull($columnCollection['column']);

                    continue;
                }

                if (! isset($columnCollection['value'])) {
                    continue;
                }

                if ($columnCollection['operator'] === 'in') {
                    $count = $count->whereIn($columnCollection['column'], $columnCollection['value']);

                    continue;
                }
                $count->where($columnCollection['column'], $columnCollection['operator'], $columnCollection['value']);
            }
        }

        if ($count->count() != 0) {
            $fail($this->message ?? 'The :attribute has already been taken.');
        }
    }
}
