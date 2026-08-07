<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits;

use Illuminate\Support\Arr;

trait WithPullIntoJsonColumn
{
    /**
     * @param array<string, mixed> $modelData
     * @param string               $column
     * @param array<int, string>   $fields
     *
     * @return array<string, mixed>
     */
    protected function pullIntoJsonColumn(array $modelData, string $column, array $fields): array
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $modelData)) {
                continue;
            }

            $value = Arr::pull($modelData, $field);

            if ($value === null || $value === '') {
                continue;
            }

            $modelData[$column][$field] = $value;
        }

        return $modelData;
    }
}
