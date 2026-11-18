<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 08:52
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Exports\SupplyChain;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The sheet a supplier fills with the new products it wants to propose, one per row, and uploads from the Suggest a
 * product form of its page. The columns are read by position, in the order of COLUMNS.
 */
class SupplierProductProposalTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public const array COLUMNS = [
        'name'           => 'Product name',
        'description'    => 'Description',
        'proposed_price' => 'Proposed price',
        'currency'       => 'Currency',
        'unit_basis'     => 'Price is per',
        'moq'            => 'Minimum order',
        'lead_time_days' => 'Lead time, days',
        'dimensions'     => 'Dimensions',
        'materials'      => 'Materials',
        'packaging'      => 'Packaging',
    ];

    public const array REQUIRED = ['name', 'description', 'proposed_price', 'unit_basis'];

    public function __construct(private readonly ?string $currency)
    {
    }

    public function headings(): array
    {
        return collect(self::COLUMNS)
            ->map(fn (string $heading, string $field) => in_array($field, self::REQUIRED, true) ? $heading.' *' : $heading)
            ->values()
            ->all();
    }

    public function array(): array
    {
        return [
            collect(self::COLUMNS)->map(fn (string $heading, string $field) => $field === 'currency' ? $this->currency : null)->values()->all(),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function title(): string
    {
        return 'New products';
    }
}
