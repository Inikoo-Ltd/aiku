<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 19 Oct 2022 18:36:28 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateEmployees;
use App\Actions\HumanResources\JobPosition\Hydrators\JobPositionHydrateGuests;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\HumanResources\JobPosition;

class HydrateJobPosition
{
    use WithHydrateCommand;
    public string $commandSignature = 'hydrate:job_positions {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = JobPosition::class;
    }

    public function handle(JobPosition $jobPosition): void
    {
        JobPositionHydrateEmployees::run($jobPosition);
        JobPositionHydrateGuests::run($jobPosition);
    }


}
