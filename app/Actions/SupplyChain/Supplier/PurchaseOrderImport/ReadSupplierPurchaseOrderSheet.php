<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\PurchaseOrderImport;

use App\Actions\SupplyChain\SupplierProduct\Upload\ReadSupplierProductSheet;
use App\Enums\SupplyChain\SupplierProductUpload\SupplierProductSheetColumnEnum as Column;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Reads only what an order needs from the supplier parts template: the product codes and one
 * "Order Cartons <key>" column per organisation, so a sheet missing the product data columns
 * still orders.
 */
class ReadSupplierPurchaseOrderSheet extends ReadSupplierProductSheet
{
    /**
     * @return array{
     *     errors: list<string>,
     *     order_columns: array<string, string>,
     *     rows: list<array{row: int, part_reference: ?string, supplier_code: ?string, name: ?string, units_per_carton: ?int, order: array<string, array{value: mixed, text: ?string, currencies: list<string>}>}>
     * }
     */
    public function handle(string $path): array
    {
        $result = ['errors' => [], 'order_columns' => [], 'rows' => []];

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
            $result['errors'][] = __('No heading row found: none of the first 20 rows has a ":heading" column.', ['heading' => Column::PART_REFERENCE->heading()]);

            return $result;
        }

        $wanted  = [Column::PART_REFERENCE, Column::SUPPLIER_CODE, Column::UNIT_NAME, Column::UNITS_PER_SKO, Column::SKOS_PER_CARTON];
        $columns = [];
        foreach ($worksheet->getRowIterator($headingRow, $headingRow)->current()->getCellIterator() as $cell) {
            $heading = Column::normaliseHeading($cell->getValue());
            $column  = Column::fromHeading($heading);
            if ($column && in_array($column, $wanted, true) && !in_array($column->value, $columns, true)) {
                $columns[$cell->getColumn()] = $column->value;
            } elseif (str_starts_with($heading, Column::ORDER_CARTONS_PREFIX)) {
                $result['order_columns'][$cell->getColumn()] = strtoupper(trim(substr($heading, strlen(Column::ORDER_CARTONS_PREFIX))));
            }
        }

        if ($result['order_columns'] === []) {
            $result['errors'][] = __('No ":heading" column found, the sheet has no cartons to order.', ['heading' => 'Order Cartons UK']);

            return $result;
        }

        $lastRow = $worksheet->getHighestDataRow();
        for ($row = $headingRow + 1; $row <= $lastRow; $row++) {
            $cells = [];
            foreach ($columns as $letter => $key) {
                $cells[$key] = $this->readCell($worksheet->getCell($letter.$row))['text'];
            }

            $order = [];
            foreach ($result['order_columns'] as $letter => $organisationKey) {
                $order[$organisationKey] = $this->readCell($worksheet->getCell($letter.$row));
            }

            $partReference = $cells[Column::PART_REFERENCE->value] ?? null;
            $supplierCode  = $cells[Column::SUPPLIER_CODE->value] ?? null;
            if ($partReference === null && $supplierCode === null && collect($order)->every(fn (array $cell) => $cell['text'] === null)) {
                continue;
            }

            $unitsPerSko   = $cells[Column::UNITS_PER_SKO->value] ?? null;
            $skosPerCarton = $cells[Column::SKOS_PER_CARTON->value] ?? null;

            $result['rows'][] = [
                'row'              => $row,
                'part_reference'   => $partReference,
                'supplier_code'    => $supplierCode,
                'name'             => $cells[Column::UNIT_NAME->value] ?? null,
                'units_per_carton' => is_numeric($unitsPerSko) && is_numeric($skosPerCarton) ? (int) round((float) $unitsPerSko * (float) $skosPerCarton) : null,
                'order'            => $order,
            ];
        }

        return $result;
    }
}
