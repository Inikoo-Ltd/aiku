<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\HumanResources\Leave\UI\DashboardLeave;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class PrintCalendar extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    /**
     * @param Organisation $organisation
     * @param ActionRequest $request
     *
     * @return Response
     */
    public function handle(Organisation $organisation, ActionRequest $request): Response
    {
        $dashboardLeave = new DashboardLeave();
        $dashboardLeave->initialisation($organisation, $request);

        $printData = $this->getPrintData($organisation, $request);

        return Inertia::render('Org/HumanResources/PrintCalendar', [
            'title' => __('Leave Calendar - Print View'),
            'calendarData' => $printData['calendarData'],
            'weeks' => $printData['weeks'],
            'holidays' => $printData['holidays'],
            'visibleRange' => $printData['visibleRange'],
            'filters' => $printData['filters'],
            'organisation' => [
                'name' => $organisation->name,
            ],
        ]);
    }

    private function getPrintData(Organisation $organisation, ActionRequest $request): array
    {
        return [
            'calendarData' => [],
            'weeks' => [],
            'holidays' => [],
            'visibleRange' => ['start' => now()->format('Y-m-d'), 'end' => now()->format('Y-m-d')],
            'filters' => [
                'year' => now()->year,
                'month' => now()->month,
            ],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }
}
