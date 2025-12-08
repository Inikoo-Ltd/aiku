<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 01 Oct 2024 10:50:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\HumanResources\JobPosition;
use Illuminate\Support\Arr;

trait WithPreparePositionsForValidation
{
    public function prepareJobPositionsForValidation(): void
    {

        if ($this->get('permissions')) {
            $newData = [];

            foreach ($this->get('permissions') as $jobPositionCode => $position) {

                if ($jobPositionCode == 'shop-admin') {
                    $newData[] = [
                        'code' => $jobPositionCode,
                        'scopes' => array_map(function ($scope) {
                            return [
                                'slug' => $scope,
                            ];
                        }, $position),
                    ];
                } else {
                    $newData[] = match (Arr::get(explode('-', $jobPositionCode), 0)) {
                        'wah', 'dist', 'ful', 'web', 'mrk', 'cus', 'shk', => [
                            'code' => $jobPositionCode,
                            'scopes' => array_map(function ($scope) {
                                return [
                                    'slug' => $scope,
                                ];
                            }, $position),
                        ],

                        default => [
                            'code' => $jobPositionCode,
                            'scopes' => [],
                        ]
                    };
                }
            }

            foreach ($newData as $key => $data) {
                if (in_array($data['code'], [
                    'group-admin',
                    'sys-admin',
                    'gp-sc',
                    'gp-g',
                ])) {
                    $jobPosition = JobPosition::where('code', $data['code'])->where('group_id', $this->group->id)->first();
                } else {
                    $jobPosition = JobPosition::where('code', $data['code'])->where('organisation_id', $this->organisation->id)->first();
                }
                if ($jobPosition) {
                    $newData[$key]['slug'] = $jobPosition->slug;
                }

            }

            $jobPositions = [
                'job_positions' => $newData,
            ];

            $this->fill($jobPositions);
        }
    }
}
