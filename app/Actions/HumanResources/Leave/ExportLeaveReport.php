<?php

namespace App\Actions\HumanResources\Leave;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\LeavesExport;
use App\Models\HumanResources\Leave;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportLeaveReport extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;

    private const LARGE_EXPORT_THRESHOLD = 20000;

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
            ]
        );

        $this->applyTeamScope($filters);

        $query = Leave::query()
            ->where('organisation_id', $this->organisation->id)
            ->with(['employee', 'approver']);

        $this->applyFilters($query, $filters);

        $count = $query->count();

        $this->logExport($filters, $modelData['format'], $count);

        if ($count >= self::LARGE_EXPORT_THRESHOLD) {
            $this->queueExport($filters, $modelData['format']);

            if (!request()->expectsJson()) {
                return back()->with('notification', [
                    'type' => 'success',
                    'title' => __('Export queued'),
                    'description' => __('Export has been queued and will be available shortly.'),
                ]);
            }

            return response()->json([
                'message' => __('Export has been queued and will be available shortly.'),
                'queued' => true,
            ]);
        }

        $export = new LeavesExport($this->organisation->id, $filters);

        return $this->export($export, 'leaves', $modelData['format']);
    }

    private function applyTeamScope(array &$filters): void
    {
        if ($this->canEdit) {
            return;
        }

        $user = request()->user();
        $userEmployee = $user->employees()
            ->where('organisation_id', $this->organisation->id)
            ->first();

        if (!$userEmployee) {
            return;
        }

        $userJobPositions = $userEmployee->jobPositions;

        if ($userJobPositions->isEmpty()) {
            return;
        }

        $departments = $userJobPositions->pluck('department')->filter()->unique()->values()->all();
        $teams = $userJobPositions->pluck('team')->filter()->unique()->values()->all();

        if (!empty($departments)) {
            $filters['departments'] = $departments;
        }
        if (!empty($teams)) {
            $filters['teams'] = $teams;
        }
    }

    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['from'])) {
            $query->where('start_date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('end_date', '<=', $filters['to']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (!empty($filters['department'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('department', $filters['department']);
            });
        }
        if (!empty($filters['team'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('team', $filters['team']);
            });
        }
        if (!empty($filters['departments'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('department', $filters['departments']);
            });
        }
        if (!empty($filters['teams'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('team', $filters['teams']);
            });
        }
    }

    private function logExport(array $filters, string $format, int $count): void
    {
        $user = request()->user();

        logger('Leave report exported', [
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'organisation_id' => $this->organisation->id,
            'filters' => $filters,
            'format' => $format,
            'record_count' => $count,
        ]);
    }

    private function queueExport(array $filters, string $format): void
    {
        ExportLeaveReportJob::dispatch(
            $this->organisation->id,
            $filters,
            $format,
            request()->user()->id
        );
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
