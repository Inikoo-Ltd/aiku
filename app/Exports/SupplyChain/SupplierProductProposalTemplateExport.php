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
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * The sheet a supplier fills with the new products it wants to propose, one per row, and uploads from the Suggest a
 * product form of its page. Every heading carries a note on what to write; the worked example is shown on the page
 * next to the download, so the sheet starts empty. The columns are read by position, in the order of COLUMNS.
 */
class SupplierProductProposalTemplateExport implements FromArray, WithHeadings, WithStyles, WithTitle, WithEvents, ShouldAutoSize
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

    /**
     * @return array<string, string|int|float>
     */
    public static function example(?string $currency): array
    {
        return [
            'name'           => 'Mango wood bowl 20 cm',
            'description'    => 'Hand carved mango wood bowl with a food safe oil finish. Each piece has its own grain.',
            'proposed_price' => 4.5,
            'currency'       => $currency ?? 'USD',
            'unit_basis'     => 'piece',
            'moq'            => '100 pieces',
            'lead_time_days' => 45,
            'dimensions'     => '20 x 20 x 7 cm',
            'materials'      => 'Mango wood, food safe oil',
            'packaging'      => 'Each in a paper bag, 24 per carton',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function notes(): array
    {
        return [
            'name'           => 'Required. The name you give the product.',
            'description'    => 'Required. What the product is, what it is made of and what makes it sell.',
            'proposed_price' => 'Required. Your price for one "Price is per", as a number only: 4.5, not $4.50.',
            'currency'       => 'Three letter code, e.g. USD, EUR, CNY. Left empty: :currency.',
            'unit_basis'     => 'Required. What the price is for: piece, set of 3, pack of 6, kg…',
            'moq'            => 'The smallest order you accept, e.g. 100 pieces or 2 cartons.',
            'lead_time_days' => 'Days from our order until the goods are ready to ship, as a number.',
            'dimensions'     => 'Size of one product, e.g. 20 x 20 x 7 cm.',
            'materials'      => 'Everything the product is made of.',
            'packaging'      => 'How each product is packed and how many go in one carton.',
        ];
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
        return [];
    }

    /**
     * What the supplier page shows next to the download: each column with what to write in it and an example.
     *
     * @return list<array{field: string, heading: string, required: bool, note: string, example: string|int|float}>
     */
    public static function guide(?string $currency): array
    {
        $notes   = self::notes();
        $example = self::example($currency);

        return collect(self::COLUMNS)->map(fn (string $heading, string $field) => [
            'field'    => $field,
            'heading'  => __($heading),
            'required' => in_array($field, self::REQUIRED, true),
            'note'     => __($notes[$field], ['currency' => $currency ?? __('your usual currency')]),
            'example'  => $example[$field],
        ])->values()->all();
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                foreach (array_values(self::notes()) as $position => $note) {
                    $comment = $sheet->getComment(Coordinate::stringFromColumnIndex($position + 1).'1');
                    $comment->getText()->createText(str_replace(':currency', $this->currency ?? 'your usual currency', $note));
                    $comment->setWidth('260pt')->setHeight('80pt');
                }
                $sheet->freezePane('A2');
            },
        ];
    }

    public function title(): string
    {
        return 'New products';
    }
}
