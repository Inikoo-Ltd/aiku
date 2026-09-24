<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Models\HumanResources\JobPosition;
use Lorisleiva\Actions\Concerns\AsObject;

class DropLowerGradeJobPositionScopes
{
    use AsObject;

    /**
     * Positions of one department, highest grade first. Positions sharing a grade can be held together.
     */
    private const array GRADES = [
        [['hr-m'], ['hr-c']],
        [['acc-m'], ['acc-c']],
        [['shk-m'], ['shk-c']],
        [['mrk-m'], ['mrk-c']],
        [['cus-m'], ['cus-c'], ['cus-v']],
        [['wah-m'], ['wah-sc']],
        [['dist-m'], ['dist-pik', 'dist-excp-pick', 'dist-pak']],
        [['prod-m'], ['prod-p', 'prod-d', 'prod-c']],
        [['ful-m'], ['ful-wc', 'ful-c']],
    ];

    /**
     * @param  array<int, array<string, array<int>>>  $jobPositions  scopes keyed by job position id
     *
     * @return array<int, array<string, array<int>>>
     */
    public function handle(array $jobPositions): array
    {
        if (count($jobPositions) < 2) {
            return $jobPositions;
        }

        $codes = JobPosition::whereIn('id', array_keys($jobPositions))->pluck('code', 'id')->all();

        foreach (self::GRADES as $grades) {
            $higherGradeScopes = [];
            foreach ($grades as $gradeCodes) {
                $gradeScopes = [];
                foreach ($jobPositions as $jobPositionId => $scopes) {
                    if (!in_array($codes[$jobPositionId] ?? null, $gradeCodes, true)) {
                        continue;
                    }
                    $remainingScopes = $this->subtractScopes($scopes, $higherGradeScopes);
                    if ($remainingScopes === null) {
                        unset($jobPositions[$jobPositionId]);
                        continue;
                    }
                    $jobPositions[$jobPositionId] = $remainingScopes;
                    $gradeScopes[]                = $remainingScopes;
                }
                array_push($higherGradeScopes, ...$gradeScopes);
            }
        }

        return $jobPositions;
    }

    /**
     * An empty scope list covers the whole organisation. Returns null when nothing is left.
     */
    private function subtractScopes(array $scopes, array $higherGradeScopes): ?array
    {
        foreach ($higherGradeScopes as $higherScopes) {
            if (empty($scopes) || empty($higherScopes)) {
                return null;
            }
            foreach ($scopes as $model => $ids) {
                $scopes[$model] = array_values(array_diff($ids, $higherScopes[$model] ?? []));
            }
            if (collect($scopes)->flatten()->isEmpty()) {
                return null;
            }
        }

        return $scopes;
    }
}
