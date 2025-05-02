<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\HumanResources\ClockingMachine;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteClockingMachine extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    public function handle(ClockingMachine $clockingMachine): ClockingMachine
    {
        $clockingMachine->clockings()->delete();
        $clockingMachine->delete();

        return $clockingMachine;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("human-resources.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachine
    {
        $this->initialisation($organisation, $request);

        return $this->handle($clockingMachine);
    }


    public function htmlResponse(ClockingMachine $clockingMachine): RedirectResponse
    {
        return Redirect::route('grp.org.hr.workplaces.show', $clockingMachine->workplace->slug);
    }


}
