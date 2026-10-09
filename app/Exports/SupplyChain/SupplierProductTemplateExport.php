<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 06 Aug 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Exports\SupplyChain;

use App\Enums\SupplyChain\SupplierProductUpload\PackagingComponentSheetColumnEnum;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * The v7 template: Product data (v6 columns, then the GPSR / regulatory / EUDR ones), Packaging components
 * (PPWR / EPR) and the signed Supplier declarations.
 */
class SupplierProductTemplateExport implements WithMultipleSheets
{
    public const array ORDER_COLUMNS = ['UK', 'SK', 'ES', 'Aroma'];

    public const array DECLARATION_STATEMENTS = [
        'No packaging component contains lead, cadmium, mercury and hexavalent chromium above 100 mg/kg in total (PPWR Art 5)',
        'No food-contact packaging contains intentionally added PFAS (PPWR Art 5)',
        'No product or packaging contains a REACH SVHC above 0.1% w/w, unless named on Product data',
        'The materials and weights on the Packaging components tab are correct',
        'Wood, paper, palm oil, rubber and other EUDR raw materials are legally produced and deforestation-free since 31 Dec 2020, with the plots given on Product data',
        'Every product meets the safety rules of its category and carries the warnings and markings given on Product data (GPSR)',
        'We will tell AW before changing any material, packaging, factory or origin',
    ];

    public function sheets(): array
    {
        return [
            $this->sheet('Product data', $this->array()),
            $this->sheet('Packaging components', [
                array_map(fn (PackagingComponentSheetColumnEnum $column) => in_array($column, [PackagingComponentSheetColumnEnum::PART_REFERENCE, PackagingComponentSheetColumnEnum::PACKAGING_LEVEL, PackagingComponentSheetColumnEnum::COMPONENT], true) ? 'Required' : 'Opt', PackagingComponentSheetColumnEnum::cases()),
                array_map(fn (PackagingComponentSheetColumnEnum $column) => $column->heading(), PackagingComponentSheetColumnEnum::cases()),
            ]),
            $this->sheet('Supplier declarations', [
                ['Company', ''],
                ['Signed by', ''],
                ['Position', ''],
                ['Date', ''],
                [],
                ['Statement', 'Answer'],
                ...array_map(fn (string $statement) => [$statement, ''], self::DECLARATION_STATEMENTS),
            ]),
        ];
    }

    /**
     * @param list<list<mixed>> $rows
     */
    protected function sheet(string $title, array $rows): object
    {
        return new class ($title, $rows) implements FromArray, WithTitle {
            public function __construct(private readonly string $title, private readonly array $rows)
            {
            }

            public function array(): array
            {
                return $this->rows;
            }

            public function title(): string
            {
                return $this->title;
            }
        };
    }

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        return [
            ...array_map(fn (SupplierProductSheetColumnEnum $column) => $column->heading(), SupplierProductSheetColumnEnum::cases()),
            ...array_map(fn (string $organisation) => 'Order Cartons '.$organisation, self::ORDER_COLUMNS),
        ];
    }

    public function array(): array
    {
        return [
            [
                ...array_map(fn (SupplierProductSheetColumnEnum $column) => $column->isRequired() ? 'Required' : 'Opt', SupplierProductSheetColumnEnum::cases()),
                ...array_fill(0, count(self::ORDER_COLUMNS), 'Opt'),
            ],
            self::headings(),
        ];
    }
}
