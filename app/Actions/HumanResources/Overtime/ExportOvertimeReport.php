<?php

namespace App\Actions\HumanResources\Overtime;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesAuthorisation;
use App\Actions\Traits\WithExportData;
use App\Exports\HumanResources\OvertimeRequestsExport;
use App\Models\HumanResources\OvertimeRequest;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExportOvertimeReport extends OrgAction
{
    use WithHumanResourcesAuthorisation;
    use WithExportData;

    private const LARGE_EXPORT_THRESHOLD = 20000;

    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'type' => ['nullable', 'integer'],
            'status' => ['nullable', 'string'],
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
                'employee_id' => $modelData['employee_id'] ?? null,
            ]
        );

        $query = OvertimeRequest::query()
            ->where('organisation_id', $this->organisation->id);

        if (!empty($filters['from'])) {
            $query->where('requested_date', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('requested_date', '<=', $filters['to']);
        }

        if (!empty($filters['type'])) {
            $query->where('overtime_type_id', $filters['type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        $count = $query->count();

        if ($count >= self::LARGE_EXPORT_THRESHOLD) {
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

        $export = new OvertimeRequestsExport($this->organisation->id, $filters);

        return $this->export($export, 'overtime_requests', $modelData['format']);
    }

    public function asController(Organisation $organisation, ActionRequest $request): BinaryFileResponse|JsonResponse|RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }
}
