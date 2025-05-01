<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Clocking;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Clocking;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\Workplace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteClocking extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Clocking $clocking): Clocking
    {
        $clocking->delete();

        return $clocking;
    }


    public function asController(Clocking $clocking, ActionRequest $request): Clocking
    {
        $this->initialisation($clocking->organisation, $request);

        return $this->handle($clocking);
    }

    public function htmlResponse(Workplace|ClockingMachine|Clocking $parent): RedirectResponse
    {
        if (class_basename($parent::class) == 'ClockingMachine') {
            return Redirect::route(
                route: 'grp.org.hr.workplace.show.clocking_machines.show.clockings.index',
                parameters: [
                    'organisation' => $parent->organisation->slug,
                    'workplace' => $parent->workplace->slug,
                    'clockingMachine' => $parent->slug
                ]
            );
        } elseif (class_basename($parent::class) == 'Workplace') {
            return Redirect::route(
                route: 'grp.org.hr.clocking_machines.show.clockings.index',
                parameters: [
                    'organisation' => $parent->organisation->slug,
                    'workplace'    => $parent->slug
                ]
            );
        } else {
            return Redirect::route('grp.org.hr.clockings.index');
        }
    }

}
