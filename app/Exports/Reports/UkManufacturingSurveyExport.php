<?php

namespace App\Exports\Reports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UkManufacturingSurveyExport implements WithMultipleSheets
{
    public function __construct(
        protected int $organisationId,
        protected string $startDate,
        protected string $endDate,
    ) {
    }

    public function sheets(): array
    {
        return [
            new UkManufacturingSurveyAllDataSheet($this->organisationId, $this->startDate, $this->endDate),
            new UkManufacturingSurveyManufacturedSheet($this->organisationId, $this->startDate, $this->endDate),
            new UkManufacturingSurveyMerchandisedSheet($this->organisationId, $this->startDate, $this->endDate),
        ];
    }
}
