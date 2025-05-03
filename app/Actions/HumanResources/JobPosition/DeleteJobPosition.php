<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\JobPosition;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\JobPosition;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteJobPosition extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(JobPosition $jobPosition): JobPosition
    {
        $jobPosition->delete();

        return $jobPosition;
    }

    public function asController(Organisation $organisation, JobPosition $jobPosition, ActionRequest $request): JobPosition
    {
        $this->initialisation($organisation, $request);

        return $this->handle($jobPosition);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.hr.job_positions.index', [
            'organisation' => $this->organisation->slug
        ]);
    }

}
