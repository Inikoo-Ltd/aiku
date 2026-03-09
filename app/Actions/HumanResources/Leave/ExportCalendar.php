<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\HumanResources\Leave\Traits\WithLeaveCalendarData;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\CalendarExport;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportCalendar extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;
    use WithLeaveCalendarData;

    private const LARGE_EXPORT_THRESHOLD = 5000;

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => [
                'nullable',
                'string',
                Rule::exists('leave_types', 'code')->where(function ($query) {
                    $query->where('organisation_id', $this->organisation->id);
                }),
            ],
            'status' => ['nullable', 'string'],
            'department' => ['nullable', 'string', 'max:255'],
            'team' => ['nullable', 'string', 'max:255'],
            'employee_id' => ['nullable', 'integer'],
            'format' => ['required', 'string', 'in:csv,xlsx'],
        ];
    }

    public function handle(array $modelData): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $filters = array_filter(
            [
                'from' => $modelData['from'] ?? null,
                'to' => $modelData['to'] ?? null,
                'type' => $modelData['type'] ?? null,
                'status' => $modelData['status'] ?? null,
                'department' => $modelData['department'] ?? null,
                'team' => $modelData['team'] ?? null,
                'employee_id' => $modelData['employee_id'] ?? null,
                'year' => $modelData['year'] ?? Carbon::now()->year,
                'month' => $modelData['month'] ?? Carbon::now()->month,
            ]
        );

        $this->applyTeamScope($filters);

        try {
            $calendarData = $this->getCalendarData($filters);
            $weeks = $this->generateCalendarWeeks($filters);
            $holidays = $this->getHolidays($filters);

            $this->logExport($filters, $modelData['format'], count($calendarData));

            $export = new CalendarExport(
                $this->organisation,
                $filters,
                $calendarData,
                $weeks,
                $holidays,
            );

            return $this->export($export, 'leave-calendar', $modelData['format']);
        } catch (\Exception $e) {
            logger('Export calendar error: ' . $e->getMessage(), [
                'filters' => $filters,
                'trace' => $e->getTraceAsString()
            ]);

            if (request()->expectsJson()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to export calendar: ' . $e->getMessage());
        }
    }

    private function logExport(array $filters, string $format, int $count): void
    {
        $user = request()->user();

        logger('Leave calendar exported', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'organisation_id' => $this->organisation->id,
            'filters' => $filters,
            'format' => $format,
            'record_count' => $count,
        ]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
