<?php

namespace App\Exports\HumanResources;

use App\Enums\HumanResources\Leave\LeaveStatusEnum;
use App\Enums\HumanResources\Leave\LeaveTypeEnum;
use App\Models\HumanResources\Leave;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LeavesExport implements FromQuery, WithMapping, ShouldAutoSize, WithHeadings
{
    private ?int $organisationId = null;
    private ?array $filters = null;

    public function __construct(?int $organisationId = null, ?array $filters = null)
    {
        $this->organisationId = $organisationId;
        $this->filters = $filters;
    }

    public function query(): Relation|Builder|Leave
    {
        $query = Leave::query()
            ->with(['employee', 'approver']);

        if ($this->organisationId) {
            $query->where('organisation_id', $this->organisationId);
        }

        if ($this->filters) {
            if (!empty($this->filters['from'])) {
                $query->where('start_date', '>=', $this->filters['from']);
            }
            if (!empty($this->filters['to'])) {
                $query->where('end_date', '<=', $this->filters['to']);
            }
            if (!empty($this->filters['type'])) {
                $query->where('type', $this->filters['type']);
            }
            if (!empty($this->filters['status'])) {
                $query->where('status', $this->filters['status']);
            }
            if (!empty($this->filters['employee_id'])) {
                $query->where('employee_id', $this->filters['employee_id']);
            }
            if (!empty($this->filters['department'])) {
                $query->whereHas('employee', function ($q) {
                    $q->where('department', $this->filters['department']);
                });
            }
            if (!empty($this->filters['team'])) {
                $query->whereHas('employee', function ($q) {
                    $q->where('team', $this->filters['team']);
                });
            }
        }

        return $query;
    }

    public function map($row): array
    {
        $employee = $row->employee;
        $approver = $row->approver;
        $typeValue = $row->type?->value ?? $row->type;
        $statusValue = $row->status?->value ?? $row->status;

        return [
            $employee?->contact_name ?? $row->employee_name,
            $employee?->email ?? $employee?->work_email ?? '-',
            $employee?->department ?? '-',
            LeaveTypeEnum::labels()[$typeValue] ?? (string) $typeValue,
            $row->start_date->format('Y-m-d'),
            $row->end_date->format('Y-m-d'),
            $row->duration_days,
            LeaveStatusEnum::labels()[$statusValue] ?? (string) $statusValue,
            $approver?->name ?? '-',
            $row->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function headings(): array
    {
        return [
            __('Employee Name'),
            __('Employee Email'),
            __('Department'),
            __('Leave Type'),
            __('Start Date'),
            __('End Date'),
            __('Total Days'),
            __('Status'),
            __('Approver'),
            __('Created At'),
        ];
    }
}
