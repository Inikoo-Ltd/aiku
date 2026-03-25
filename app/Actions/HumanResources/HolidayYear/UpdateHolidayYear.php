<?php

namespace App\Actions\HumanResources\HolidayYear;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\HolidayYear;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UpdateHolidayYear extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(HolidayYear $holidayYear, array $modelData): HolidayYear
    {
        unset($modelData['organisation_id'], $modelData['group_id']);

        if (($modelData['is_active'] ?? false) === true) {
            HolidayYear::query()
                ->where('organisation_id', $holidayYear->organisation_id)
                ->whereKeyNot($holidayYear->id)
                ->update(['is_active' => false]);
        }

        $holidayYear->update($modelData);

        return $holidayYear->refresh();
    }

    public function rules(): array
    {
        return [
            'label'      => ['sometimes', 'required', 'string', 'max:255'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date'   => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }

    public function action(HolidayYear $holidayYear, array $modelData): HolidayYear
    {
        $this->asAction = true;
        $this->initialisation($holidayYear->organisation, $modelData);

        return $this->handle($holidayYear, $this->validatedData);
    }

    public function asController(Organisation $organisation, HolidayYear $holidayYear, ActionRequest $request): HolidayYear
    {
        $this->initialisation($organisation, $request);

        if ($holidayYear->organisation_id !== $organisation->id) {
            abort(404);
        }

        return $this->handle($holidayYear, $this->validatedData);
    }

    public function htmlResponse(HolidayYear $holidayYear): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Holiday year successfully updated.'),
        ]);

        return Redirect::back();
    }
}
