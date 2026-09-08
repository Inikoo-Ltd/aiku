<?php

namespace App\Exports\HumanResources;

use App\Enums\UI\HumanResources\TimesheetEmployeeViewEnum;
use App\Exports\HumanResources\Sheets\ClockingsByEmployeeSheet;
use App\Exports\HumanResources\Sheets\TimesheetsByEmployeeSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimesheetsByEmployeeExport implements WithMultipleSheets
{
    /**
     * @param  array<int, int>  $employeeIds
     */
    public function __construct(
        private readonly Collection $rows,
        private readonly TimesheetEmployeeViewEnum $view,
        private readonly int $organisationId,
        private readonly array $employeeIds,
        private readonly string $from,
        private readonly string $to,
        private readonly string $timezone,
    ) {
    }

    public function sheets(): array
    {
        return [
            new TimesheetsByEmployeeSheet($this->rows, $this->view),
            new ClockingsByEmployeeSheet($this->organisationId, $this->employeeIds, $this->from, $this->to, $this->timezone),
        ];
    }
}
