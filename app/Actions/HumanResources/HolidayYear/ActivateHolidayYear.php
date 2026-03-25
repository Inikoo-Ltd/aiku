<?php

namespace App\Actions\HumanResources\HolidayYear;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\HolidayYear;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ActivateHolidayYear extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(Organisation $organisation, HolidayYear $holidayYear): HolidayYear
    {
        $organisation->holidayYears()
            ->where('id', '!=', $holidayYear->id)
            ->update(['is_active' => false]);

        $holidayYear->update(['is_active' => true]);

        return $holidayYear;
    }

    public function asController(Organisation $organisation, HolidayYear $holidayYear, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        $this->handle($organisation, $holidayYear);

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Holiday year activated successfully.'),
        ]);

        return Redirect::back();
    }
}
