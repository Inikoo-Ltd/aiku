<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\WorkSchedule;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\WorkSchedule;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteWorkSchedule extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(WorkSchedule $workSchedule): bool
    {
        return $workSchedule->delete();
    }

    public function action(WorkSchedule $workSchedule): bool
    {
        return $this->handle($workSchedule);
    }

    public function asController(Organisation $organisation, WorkSchedule $workSchedule, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($workSchedule);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Shift schedule successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
