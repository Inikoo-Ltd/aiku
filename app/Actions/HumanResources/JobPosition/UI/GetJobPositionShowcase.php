<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Thu, 25 May 2023 15:03:06 Central European Summer Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\HumanResources\JobPosition\UI;

use App\Http\Resources\HumanResources\JobPositionResource;
use App\Models\HumanResources\JobPosition;
use Lorisleiva\Actions\Concerns\AsObject;

class GetJobPositionShowcase
{
    use AsObject;

    public function handle(JobPosition $jobPosition): array
    {
        return [
            'jobPosition' => JobPositionResource::make($jobPosition),
        ];
    }
}
