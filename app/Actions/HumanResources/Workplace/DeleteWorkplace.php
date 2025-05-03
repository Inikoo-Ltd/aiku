<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 21 Sep 2023 11:34:13 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Workplace;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Workplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteWorkplace extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Workplace $workplace): Workplace
    {
        $workplace->delete();

        return $workplace;
    }

    public function asController(Workplace $workplace, ActionRequest $request): Workplace
    {
        $this->initialisation($workplace->organisation, $request);

        return $this->handle($workplace);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.hr.workplaces.index');
    }

}
