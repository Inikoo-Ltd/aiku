<?php

namespace App\Exports\HumanResources;

use App\Exports\HumanResources\Sheets\ClockingsByDateSheet;
use App\Exports\HumanResources\Sheets\TimesheetsByDateSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TimesheetsByDateExport implements WithMultipleSheets
{
    /**
     * @param  array<int, int>  $employeeIds
     */
    public function __construct(
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
            new TimesheetsByDateSheet($this->organisationId, $this->employeeIds, $this->from, $this->to, $this->timezone),
            new ClockingsByDateSheet($this->organisationId, $this->employeeIds, $this->from, $this->to, $this->timezone),
        ];
    }
}
