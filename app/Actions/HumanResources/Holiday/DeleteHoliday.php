<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Dec 2025 13:01:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Holiday;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\Holiday;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteHoliday extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Holiday $holiday): bool
    {
        return $holiday->delete();
    }

    public function action(Holiday $holiday): bool
    {
        return $this->handle($holiday);
    }

    public function asController(Organisation $organisation, Holiday $holiday, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($holiday);
    }

    public function htmlResponse(): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Holiday successfully deleted.'),
        ]);

        return Redirect::back();
    }
}
