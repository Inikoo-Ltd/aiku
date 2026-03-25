<?php

namespace App\Actions\HumanResources\Holiday;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\ActionRequest;

class GetCalendarHolidays extends OrgAction
{
    use WithHumanResourcesAuthorisation;

    /**
     * @param Organisation $organisation
     * @param ActionRequest $request
     *
     * @return JsonResponse
     */
    public function handle(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $year = $request->input('year');

        $query = $organisation->holidays();

        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $query->forDateRange($start, $end);
        } elseif ($year) {
            $query->forYear($year);
        }

        $holidays = $query->get()->map(function ($holiday) {
            return [
                'id' => $holiday->id,
                'label' => $holiday->label,
                'from' => $holiday->from->format('Y-m-d'),
                'to' => $holiday->to->format('Y-m-d'),
                'type' => $holiday->type->value,
                'days' => $holiday->from->diffInDays($holiday->to) + 1,
            ];
        });

        return response()->json($holidays);
    }

    public function asController(Organisation $organisation, ActionRequest $request): JsonResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request);
    }
}
