<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-30 14:38
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\GoodsIn\StockDelivery;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryItemsSheetColumnEnum as Column;
use Lorisleiva\Actions\Concerns\AsObject;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads a container sheet (a proforma or packing list) from any tab: the heading row is the first of the top 30 rows
 * with a code and a quantity heading, every other column and tab is ignored.
 */
class ReadStockDeliveryItemsSheet
{
    use AsObject;

    private const int HEADING_ROWS = 30;

    /**
     * Without a tab asked for, the first one named like a container list is read, or else the first tab. A CSV file
     * has a single sheet and no tabs.
     *
     * @return array{
     *     errors: list<string>,
     *     sheets: list<string>,
     *     sheet: ?string,
     *     heading_row: ?int,
     *     columns: list<array{key: string, heading: string, letter: string}>,
     *     quantity_in: ?string,
     *     rows: list<array{row: int, code: ?string, quantity: ?float, quantity_text: ?string, purchase_order: ?string, barcode: ?string, units_per_sko: ?float, description: ?string}>
     * }
     */
    public function handle(string $path, ?string $sheet = null): array
    {
        $result = ['errors' => [], 'sheets' => [], 'sheet' => null, 'heading_row' => null, 'columns' => [], 'quantity_in' => null, 'rows' => []];

        try {
            $reader = $this->reader($path);
        } catch (Throwable $e) {
            $result['errors'][] = __('The file could not be opened as a spreadsheet: :error', ['error' => $e->getMessage()]);

            return $result;
        }

        if ($reader instanceof Csv) {
            try {
                $worksheet = $reader->load($path)->getActiveSheet();
            } catch (Throwable $e) {
                $result['errors'][] = __('The CSV file could not be read: :error', ['error' => $e->getMessage()]);

                return $result;
            }
        } else {
            try {
                $result['sheets'] = $reader->listWorksheetNames($path);
            } catch (Throwable $e) {
                $result['errors'][] = __('The file could not be opened as a spreadsheet: :error', ['error' => $e->getMessage()]);

                return $result;
            }

            if ($sheet !== null && !in_array($sheet, $result['sheets'], true)) {
                $sheet = null;
            }
            $sheet ??= collect($result['sheets'])->first(fn (string $name) => preg_match('/proforma|container|packing/i', $name)) ?? $result['sheets'][0] ?? null;
            if ($sheet === null) {
                $result['errors'][] = __('The file has no tabs.');

                return $result;
            }
            $result['sheet'] = $sheet;

            try {
                $worksheet = $reader->setLoadSheetsOnly([$sheet])->load($path)->getSheetByName($sheet);
            } catch (Throwable $e) {
                $result['errors'][] = __('Tab ":sheet" could not be read: :error', ['sheet' => $sheet, 'error' => $e->getMessage()]);

                return $result;
            }
        }

        $found = $worksheet ? $this->findHeadings($worksheet) : null;
        if ($found === null) {
            $result['errors'][] = $result['sheet'] === null
                ? __('The file has no heading row: a code column (such as "Code" or "SKU") and a quantity column (such as "Total Units", "SKOs" or "Total CTN") in the first :rows rows.', ['rows' => self::HEADING_ROWS])
                : __('Tab ":sheet" has no heading row: a code column (such as "Code" or "SKU") and a quantity column (such as "Total Units", "SKOs" or "Total CTN") in the first :rows rows. Choose another tab.', ['sheet' => $result['sheet'], 'rows' => self::HEADING_ROWS]);

            return $result;
        }

        $result['heading_row'] = $found['row'];
        $result['columns']     = collect($found['columns'])->map(fn (array $column, string $key) => ['key' => $key, ...$column])->values()->all();
        $result['quantity_in'] = collect(Column::quantityColumns())->first(fn (Column $column) => isset($found['columns'][$column->value]))->value;

        $letter  = fn (Column $column) => $found['columns'][$column->value]['letter'] ?? null;
        $lastRow = $worksheet->getHighestDataRow();
        for ($row = $found['row'] + 1; $row <= $lastRow; $row++) {
            $text = fn (Column $column) => ($columnLetter = $letter($column)) ? $this->text($worksheet->getCell($columnLetter.$row)) : null;

            $code         = $text(Column::CODE);
            $quantityText = $text(Column::from($result['quantity_in']));
            if ($code === null && $quantityText === null) {
                continue;
            }

            $result['rows'][] = [
                'row'            => $row,
                'code'           => $code,
                'quantity'       => $this->number($quantityText),
                'quantity_text'  => $quantityText,
                'purchase_order' => $text(Column::PURCHASE_ORDER),
                'barcode'        => $text(Column::BARCODE),
                'units_per_sko'  => $this->number($text(Column::UNITS_PER_SKO)),
                'description'    => $text(Column::DESCRIPTION),
            ];
        }

        return $result;
    }

    private function reader(string $path): IReader
    {
        $reader = IOFactory::createReader(IOFactory::identify($path));
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        if ($reader instanceof Csv) {
            $reader->setInputEncoding(Csv::GUESS_ENCODING);
        }

        return $reader;
    }

    /**
     * @return array{row: int, columns: array<string, array{heading: string, letter: string}>}|null
     */
    private function findHeadings(Worksheet $worksheet): ?array
    {
        $lastRow = min(self::HEADING_ROWS, $worksheet->getHighestDataRow());
        for ($row = 1; $row <= $lastRow; $row++) {
            $columns = [];
            foreach ($worksheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
                $column = Column::fromHeading($cell->getValue());
                if ($column && !isset($columns[$column->value])) {
                    $columns[$column->value] = ['heading' => trim((string) $cell->getValue()), 'letter' => $cell->getColumn()];
                }
            }

            $hasQuantity = collect(Column::quantityColumns())->contains(fn (Column $column) => isset($columns[$column->value]));
            if (isset($columns[Column::CODE->value]) && $hasQuantity) {
                return ['row' => $row, 'columns' => $columns];
            }
        }

        return null;
    }

    private function text(Cell $cell): ?string
    {
        $value = $cell->isFormula() ? $cell->getOldCalculatedValue() : $cell->getValue();
        if (is_float($value) && floor($value) === $value && abs($value) < PHP_INT_MAX) {
            $value = (int) $value;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function number(?string $text): ?float
    {
        if ($text === null || !preg_match('/-?\d[\d,.\s]*/', $text, $match)) {
            return null;
        }

        $number = preg_replace('/[\s,]/', '', $match[0]);

        return is_numeric($number) ? (float) $number : null;
    }
}
