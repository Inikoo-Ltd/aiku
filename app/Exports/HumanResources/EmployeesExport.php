<?php

namespace App\Exports\HumanResources;

use App\Models\HumanResources\Employee;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromQuery, ShouldAutoSize, WithHeadings, WithMapping
{
    public function query(): Relation|\Illuminate\Database\Eloquent\Builder|Employee|Builder
    {
        return Employee::query();
    }

    /** @var Employee */
    public function map($row): array
    {
        return [
            $row->id,
            $row->slug,
            $row->worker_number,
            $row->job_title,
            $row->email,
            $row->phone,
            $row->contact_name,
            $row->salary,
            $row->working_hours,
            $row->emergency_contact,
            $row->employment_start_at,
            $row->employment_end_at,
        ];
    }

    public function headings(): array
    {
        return [
            '#',
            'Slug',
            'Worker Number',
            'Job Title',
            'Email',
            'Phone',
            'Contact Name',
            'Salary',
            'Working Hours',
            'Emergency Contact',
            'Employment Start At',
            'Employment End At',
        ];
    }
}
