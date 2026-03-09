<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\HumanResources\Leave\Traits\WithLeaveCalendarData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class PrintCalendar extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithLeaveCalendarData;

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer'],
            'year' => ['required', 'integer', 'min:2020', 'max:2030'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'format' => ['nullable', 'string', 'in:data-only'],
        ];
    }

    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $filters = array_filter(
            [
                'from' => $request->get('from'),
                'to' => $request->get('to'),
                'type' => $request->get('type'),
                'status' => $request->get('status'),
                'department' => $request->get('department'),
                'team' => $request->get('team'),
                'employee_id' => $request->get('employee_id'),
                'year' => $request->get('year', Carbon::now()->year),
                'month' => $request->get('month', Carbon::now()->month),
            ]
        );

        $this->applyTeamScope($filters);

        $calendarData = $this->getCalendarData($filters);
        $weeks = $this->generateCalendarWeeks($filters);
        $holidays = $this->getHolidays($filters);
        $visibleRange = $this->getVisibleRange($filters);

        $format = $request->get('format', 'full');
        $isDataOnly = $format === 'data-only';

        return Inertia::render('Org/HumanResources/PrintCalendar', [
            'title' => $isDataOnly ? __('Leave Calendar - Data') : __('Leave Calendar - Print View'),
            'calendarData' => $calendarData,
            'weeks' => $isDataOnly ? [] : $weeks,
            'holidays' => $isDataOnly ? [] : $holidays,
            'visibleRange' => $visibleRange,
            'filters' => $filters,
            'organisation' => [
                'name' => $organisation->name,
            ],
            'isDataOnly' => $isDataOnly,
        ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }
}
