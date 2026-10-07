<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum;
use Lorisleiva\Actions\Concerns\AsObject;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads the "Product data" tab: finds the heading row, maps columns by heading text and returns one entry per
 * filled row with each cell's value and the currencies its formatting or text points to.
 */
class ReadSupplierProductSheet
{
    use AsObject;

    /**
     * @var array<string, list<string>>
     */
    protected const array CURRENCY_SYMBOLS = [
        '£'   => ['GBP'],
        '€'   => ['EUR'],
        '₹'   => ['INR'],
        'Rs'  => ['NPR', 'INR', 'LKR', 'PKR'],
        '¥'   => ['CNY', 'JPY'],
        'RMB' => ['CNY'],
        '฿'   => ['THB'],
        '₫'   => ['VND'],
        'Rp'  => ['IDR'],
        'RM'  => ['MYR'],
        'Kč'  => ['CZK'],
        'zł'  => ['PLN'],
        '$'   => ['USD', 'HKD', 'AUD', 'CAD', 'SGD', 'NZD'],
    ];

    /**
     * @return array{
     *     errors: list<string>,
     *     heading_row: int,
     *     columns: array<string, string>,
     *     order_columns: array<string, string>,
     *     rows: list<array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>, order: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>
     * }
     */
    public function handle(string $path): array
    {
        $result = ['errors' => [], 'heading_row' => 0, 'columns' => [], 'order_columns' => [], 'rows' => []];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            $result['errors'][] = __('The file could not be opened as a spreadsheet: :error', ['error' => $e->getMessage()]);

            return $result;
        }

        $worksheet = $this->productDataSheet($spreadsheet->getAllSheets());

        $headingRow = $this->findHeadingRow($worksheet);
        if ($headingRow === null) {
            $result['errors'][] = __('No heading row found: none of the first 20 rows has a ":heading" column.', ['heading' => SupplierProductSheetColumnEnum::PART_REFERENCE->heading()]);

            return $result;
        }
        $result['heading_row'] = $headingRow;

        foreach ($worksheet->getRowIterator($headingRow, $headingRow)->current()->getCellIterator() as $cell) {
            $heading = SupplierProductSheetColumnEnum::normaliseHeading($cell->getValue());
            $column  = SupplierProductSheetColumnEnum::fromHeading($heading);
            if ($column && !in_array($column->value, $result['columns'], true)) {
                $result['columns'][$cell->getColumn()] = $column->value;
            } elseif (str_starts_with($heading, SupplierProductSheetColumnEnum::ORDER_CARTONS_PREFIX)) {
                $result['order_columns'][$cell->getColumn()] = strtoupper(trim(substr($heading, strlen(SupplierProductSheetColumnEnum::ORDER_CARTONS_PREFIX))));
            }
        }

        foreach (SupplierProductSheetColumnEnum::cases() as $column) {
            if ($column->isRequired() && !in_array($column->value, $result['columns'], true)) {
                $result['errors'][] = __('Missing column: :heading', ['heading' => $column->heading()]);
            }
        }
        if ($result['errors'] !== []) {
            return $result;
        }

        $lastRow = $worksheet->getHighestDataRow();
        for ($row = $headingRow + 1; $row <= $lastRow; $row++) {
            $cells = [];
            foreach ($result['columns'] as $letter => $key) {
                $cells[$key] = $this->readCell($worksheet->getCell($letter.$row));
            }

            if (collect($cells)->every(fn (array $cell) => $cell['text'] === null)) {
                continue;
            }

            $order = [];
            foreach ($result['order_columns'] as $letter => $organisationKey) {
                $order[$organisationKey] = $this->readCell($worksheet->getCell($letter.$row));
            }

            $result['rows'][] = ['row' => $row, 'cells' => $cells, 'order' => $order];
        }

        return $result;
    }

    /**
     * @param list<Worksheet> $worksheets
     */
    protected function productDataSheet(array $worksheets): Worksheet
    {
        foreach ($worksheets as $worksheet) {
            if (strcasecmp(trim($worksheet->getTitle()), 'product data') === 0) {
                return $worksheet;
            }
        }

        return $worksheets[0];
    }

    protected function findHeadingRow(Worksheet $worksheet): ?int
    {
        $lastRow = min(20, $worksheet->getHighestDataRow());
        for ($row = 1; $row <= $lastRow; $row++) {
            foreach ($worksheet->getRowIterator($row, $row)->current()->getCellIterator() as $cell) {
                if (SupplierProductSheetColumnEnum::fromHeading($cell->getValue()) === SupplierProductSheetColumnEnum::PART_REFERENCE) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * @return array{value: mixed, text: ?string, currencies: list<string>}
     */
    public function readCell(Cell $cell): array
    {
        try {
            $value = $cell->getCalculatedValue();
        } catch (Throwable) {
            $value = $cell->getOldCalculatedValue();
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                $value = null;
            }
        }

        $currencies = $this->currenciesIn((string)$cell->getStyle()->getNumberFormat()->getFormatCode());
        if (is_string($value)) {
            $currencies = array_values(array_unique([...$currencies, ...$this->currenciesIn($value)]));
            $number     = $this->numberIn($value);
            if ($number !== null && $this->currenciesIn($value) !== []) {
                $value = $number;
            }
        }

        return [
            'value'      => $value,
            'text'       => $value === null ? null : (string)$value,
            'currencies' => $currencies,
        ];
    }

    /**
     * @return list<string>
     */
    public function currenciesIn(string $text): array
    {
        if (preg_match('/\[\$([A-Z]{3})\b/', $text, $matches)) {
            return [$matches[1]];
        }

        foreach (self::CURRENCY_SYMBOLS as $symbol => $currencies) {
            if (str_contains($text, $symbol)) {
                return $currencies;
            }
        }

        return [];
    }

    public function numberIn(string $text): ?float
    {
        $text = preg_replace('/[^\d.,\-]/u', '', $text);
        if ($text === '' || $text === '-') {
            return null;
        }

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace(',', '', $text);
        } elseif (str_contains($text, ',')) {
            $text = preg_match('/,\d{3}$/', $text) ? str_replace(',', '', $text) : str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float)$text : null;
    }
}
