<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload;

use App\Enums\SupplyChain\SupplierProductUpload\PackagingComponentSheetColumnEnum;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use DateTime;
use Lorisleiva\Actions\Concerns\AsObject;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Reads the "Product data" tab: finds the heading row, maps columns by heading text and returns one entry per
 * filled row with each cell's value and the currencies its formatting or text points to. A v7 file also brings
 * its "Packaging components" rows and its signed "Supplier declarations" tab.
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
     *     rows: list<array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>, order: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>,
     *     packaging: list<array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>,
     *     packaging_unread: bool,
     *     declaration: ?array{company: ?string, signed_by: ?string, position: ?string, signed_on: ?string, answers: list<array{question: string, answer: string}>}
     * }
     */
    public function handle(string $path): array
    {
        $result = ['errors' => [], 'heading_row' => 0, 'columns' => [], 'order_columns' => [], 'rows' => [], 'packaging' => [], 'packaging_unread' => false, 'declaration' => null];

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $e) {
            $result['errors'][] = __('The file could not be opened as a spreadsheet: :error', ['error' => $e->getMessage()]);

            return $result;
        }

        $worksheets = $spreadsheet->getAllSheets();
        $worksheet  = $this->sheetNamed($worksheets, 'product data') ?? $worksheets[0];

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

        $packagingSheet = $this->sheetNamed($worksheets, PackagingComponentSheetColumnEnum::SHEET);
        if ($packagingSheet) {
            $packagingRows               = $this->packagingRows($packagingSheet);
            $result['packaging']         = $packagingRows ?? [];
            $result['packaging_unread']  = $packagingRows === null;
        }

        $declarationSheet = $this->sheetNamed($worksheets, 'supplier declarations') ?? $this->sheetNamed($worksheets, 'supplier declaration');
        if ($declarationSheet) {
            $result['declaration'] = $this->declaration($declarationSheet);
        }

        return $result;
    }

    /**
     * @param list<Worksheet> $worksheets
     */
    protected function sheetNamed(array $worksheets, string $title): ?Worksheet
    {
        foreach ($worksheets as $worksheet) {
            if (strcasecmp(trim($worksheet->getTitle()), $title) === 0) {
                return $worksheet;
            }
        }

        return null;
    }

    /**
     * Rows with anything filled, a blank Part reference included so the preview can name them. Null when the tab
     * has no "Part reference" heading, so nothing on it can be read.
     *
     * @return list<array{row: int, cells: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>|null
     */
    protected function packagingRows(Worksheet $worksheet): ?array
    {
        $headingRow = $this->findHeadingRow($worksheet);
        if ($headingRow === null) {
            return null;
        }

        $columns = [];
        foreach ($worksheet->getRowIterator($headingRow, $headingRow)->current()->getCellIterator() as $cell) {
            $column = PackagingComponentSheetColumnEnum::fromHeading($cell->getValue());
            if ($column && !in_array($column->value, $columns, true)) {
                $columns[$cell->getColumn()] = $column->value;
            }
        }

        $rows    = [];
        $lastRow = $worksheet->getHighestDataRow();
        for ($row = $headingRow + 1; $row <= $lastRow; $row++) {
            $cells = [];
            foreach ($columns as $letter => $key) {
                $cells[$key] = $this->readCell($worksheet->getCell($letter.$row));
            }

            if (collect($cells)->every(fn (array $cell) => $cell['text'] === null)) {
                continue;
            }

            $rows[] = ['row' => $row, 'cells' => $cells];
        }

        return $rows;
    }

    /**
     * The declaration is a form: Company, Signed by, Position and Date as label and value (in two cells or as
     * "Label: value" in one), then a "Statement | Answer" heading with one statement per row below it. A statement
     * left unanswered is kept with an empty answer so the preview can say so. Without that heading, every other
     * labelled row is taken as a statement.
     *
     * @return array{company: ?string, signed_by: ?string, position: ?string, signed_on: ?string, answers: list<array{question: string, answer: string}>}|null
     */
    protected function declaration(Worksheet $worksheet): ?array
    {
        $declaration  = ['company' => null, 'signed_by' => null, 'position' => null, 'signed_on' => null, 'answers' => []];
        $headerFields = [
            'company'   => ['company', 'supplier', 'company name', 'supplier name'],
            'signed_by' => ['signed by', 'name', 'signatory', 'name of signatory'],
            'position'  => ['position', 'job title', 'role', 'title'],
            'signed_on' => ['date', 'date signed', 'signed on'],
        ];
        $answerColumn = null;

        foreach ($worksheet->getRowIterator() as $row) {
            $filled = [];
            foreach ($row->getCellIterator() as $cell) {
                if ($this->readCell($cell)['text'] !== null) {
                    $filled[] = $cell;
                }
            }
            if ($filled === []) {
                continue;
            }

            $label = trim((string)$this->readCell($filled[0])['text']);
            $value = $filled[1] ?? null;
            if ($value === null && preg_match('/^([^:]{2,30}):\s*(.+)$/', $label, $matches)) {
                $label = trim($matches[1]);
                $value = trim($matches[2]);
            }
            $key = mb_strtolower(rtrim($label, ': '));

            if ($answerColumn === null && in_array($key, ['statement', 'question', 'declaration'], true)) {
                foreach ($filled as $cell) {
                    if (mb_strtolower(trim((string)$this->readCell($cell)['text'])) === 'answer') {
                        $answerColumn = $cell->getColumn();
                    }
                }
                $answerColumn ??= $value instanceof Cell ? $value->getColumn() : 'B';

                continue;
            }

            if ($answerColumn !== null) {
                $declaration['answers'][] = ['question' => $label, 'answer' => $this->answerText($worksheet->getCell($answerColumn.$row->getRowIndex()))];

                continue;
            }

            $field = collect($headerFields)->search(fn (array $labels) => in_array($key, $labels, true));
            if ($field !== false) {
                if ($declaration[$field] === null && $value !== null) {
                    $declaration[$field] = $field === 'signed_on' ? $this->date($value) : ($value instanceof Cell ? $this->answerText($value) : $value);
                }

                continue;
            }

            if ($value instanceof Cell && $key !== 'signature') {
                $declaration['answers'][] = ['question' => $label, 'answer' => $this->answerText($value)];
            }
        }

        if ($declaration['signed_by'] === null && collect($declaration['answers'])->every(fn (array $answer) => $answer['answer'] === '')) {
            return null;
        }

        return $declaration;
    }

    protected function answerText(Cell $cell): string
    {
        $value = $this->readCell($cell)['value'];
        if (is_bool($value)) {
            return $value ? __('Yes') : __('No');
        }

        return trim((string)$value);
    }

    /**
     * A real Excel date, or a typed one read day first (01/10/2026 is 1 October), never as a US date.
     */
    protected function date(Cell|string $cell): ?string
    {
        if ($cell instanceof Cell) {
            $value = $this->readCell($cell)['value'];
            if (is_numeric($value) && ExcelDate::isDateTime($cell)) {
                return ExcelDate::excelToDateTimeObject((float)$value)->format('Y-m-d');
            }
        } else {
            $value = $cell;
        }

        $text = trim((string)$value);
        foreach (['Y-m-d', 'j/n/Y', 'j.n.Y', 'j-n-Y', 'j/n/y', 'j F Y', 'j M Y'] as $format) {
            $date = DateTime::createFromFormat('!'.$format, $text);
            $errors = DateTime::getLastErrors();
            if ($date && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
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
