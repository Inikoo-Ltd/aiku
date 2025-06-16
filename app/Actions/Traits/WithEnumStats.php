<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 17 Sep 2023 12:01:47 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Arr;

trait WithEnumStats
{
    private function getEnumStats(
        string $model,
        string $field,
        $enum,
        $models,
        $where = false,
        $fieldStatsLabel = null
    ): array {
        $stats = [];

        $applyWhere = false;
        if ($this->isClosure($where)) {
            $applyWhere = true;
        } else {
            $where = function () {
                //
            };
        }

        if ($fieldStatsLabel === null) {
            $fieldStatsLabel = $field;
        }

        $count = $models::selectRaw("$field, count(*) as total")
            ->when(
                $applyWhere,
                $where
            )
            ->groupBy($field)
            ->pluck('total', $field)->all();
        foreach ($enum::cases() as $case) {
            $stats["number_{$model}_{$fieldStatsLabel}_".$case->snake()] = Arr::get($count, $case->value, 0);
        }

        return $stats;
    }

    public function isClosure($t): bool
    {
        return $t instanceof \Closure;
    }
}
