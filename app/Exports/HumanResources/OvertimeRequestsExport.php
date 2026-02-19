<?php

namespace App\Exports\HumanResources;

use App\Enums\HumanResources\Overtime\OvertimeRequestStatusEnum;
use App\Models\HumanResources\OvertimeRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OvertimeRequestsExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    private ?int $organisationId = null;

    private ?array $filters = null;

    public function __construct(?int $organisationId = null, ?array $filters = null)
    {
        $this->organisationId = $organisationId;
        $this->filters = $filters;
    }

    public function query(): Relation|Builder|OvertimeRequest
    {
        $query = OvertimeRequest::query()
            ->with(['employee', 'approver', 'recordedBy', 'overtimeType']);

        if ($this->organisationId) {
            $query->where('organisation_id', $this->organisationId);
        }

        if ($this->filters) {
            if (!empty($this->filters['from'])) {
                $query->where('requested_date', '>=', $this->filters['from']);
            }

            if (!empty($this->filters['to'])) {
                $query->where('requested_date', '<=', $this->filters['to']);
            }

            if (!empty($this->filters['type'])) {
                $query->where('overtime_type_id', $this->filters['type']);
            }

            if (!empty($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }

            if (!empty($this->filters['employee_id'])) {
                $query->where('employee_id', $this->filters['employee_id']);
            }
        }

        return $query;
    }

    public function map($row): array
    {
        $employee = $row->employee;
        $approver = $row->approver;
        $recorder = $row->recordedBy;
        $statusValue = $row->status?->value ?? $row->status;

        return [
            $employee?->contact_name ?? '-',
            $employee?->department ?? '-',
            $row->overtimeType?->name ?? '-',
            $row->requested_date?->format('Y-m-d'),
            $row->requested_start_at ? $row->requested_start_at->format('Y-m-d H:i') : '-',
            $row->requested_duration_minutes,
            $row->recorded_start_at ? $row->recorded_start_at->format('Y-m-d H:i') : '-',
            $row->recorded_duration_minutes ?? 0,
            OvertimeRequestStatusEnum::labels()[$statusValue] ?? (string) $statusValue,
            $row->reason ?? '-',
            $approver?->contact_name ?? '-',
            $row->approved_at ? $row->approved_at->format('Y-m-d H:i') : '-',
            $recorder?->contact_name ?? '-',
            $row->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            __('Employee Name'),
            __('Department'),
            __('Overtime Type'),
            __('Requested Date'),
            __('Requested Start'),
            __('Requested Duration (minutes)'),
            __('Recorded Start'),
            __('Recorded Duration (minutes)'),
            __('Status'),
            __('Reason/Notes'),
            __('Approved By'),
            __('Approved At'),
            __('Recorded By'),
            __('Created At'),
        ];
    }
}
