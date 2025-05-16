/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-16h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

<?php
/*
 * author Arya Permana - Kirin
 * created on 16-05-2025-14h-23m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Traits;

use Illuminate\Support\Arr;

trait WithDoubleEnumStats
{
    private function getDoubleEnumStats(
        string $model,
        string $field1,
        string $field2,
        $enum1,
        $enum2,
        $models,
        $where = false,
        $fieldStatsLabel1 = null,
        $fieldStatsLabel2 = null
    ): array {
        $stats = [];

        $applyWhere = false;
        if ($this->is_closure($where)) {
            $applyWhere = true;
        } else {
            $where = function ($q) {
            };
        }

        if ($fieldStatsLabel1 === null) {
            $fieldStatsLabel1 = $field1;
        }
        if ($fieldStatsLabel2 === null) {
            $fieldStatsLabel2 = $field2;
        }

        $count = $models::selectRaw("$field1, count(*) as total")
            ->when(
                $applyWhere,
                $where
            )
            ->groupBy($field)
            ->pluck('total', $field)->all();
        foreach ($enum1::cases() as $case1) {
            foreach ($enum2::cases() as $case2) {
                $stats["number_".$case1->snake()."_{$fieldStatsLabel1}_{$model}_{$fieldStatsLabel2}_".$case2->snake()] = Arr::get($count, $case2->value, 0);
            }
        }

        return $stats;
    }

    public function is_closure($t): bool
    {
        return $t instanceof \Closure;
    }
}
