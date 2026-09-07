<?php

namespace App\Exports\HumanResources\Sheets\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait MergesGroupedColumns
{
    /**
     * @param  array<int, string>  $columns  Column letters, outermost group first.
     */
    private function mergeColumns(Worksheet $sheet, array $columns, int $headerRow = 1): void
    {
        $highestRow = $sheet->getHighestRow();

        if ($highestRow <= $headerRow + 1) {
            return;
        }

        $firstRow = $headerRow + 1;

        $values = [];
        foreach ($columns as $column) {
            for ($row = $firstRow; $row <= $highestRow; $row++) {
                $values[$column][$row] = (string) $sheet->getCell("{$column}{$row}")->getValue();
            }
        }

        foreach ($columns as $index => $column) {
            $parentColumns = array_slice($columns, 0, $index);
            $this->mergeColumn($sheet, $column, $firstRow, $highestRow, $parentColumns, $values);
        }
    }

    /**
     * @param  array<int, string>  $parentColumns
     * @param  array<string, array<int, string>>  $values
     */
    private function mergeColumn(Worksheet $sheet, string $column, int $firstRow, int $lastRow, array $parentColumns, array $values): void
    {
        $groupStart = $firstRow;
        $previousKey = $this->rowGroupKey($values, $column, $parentColumns, $firstRow);

        for ($row = $firstRow + 1; $row <= $lastRow + 1; $row++) {
            $currentKey = $row <= $lastRow ? $this->rowGroupKey($values, $column, $parentColumns, $row) : null;

            if ($currentKey !== $previousKey) {
                $groupEnd = $row - 1;

                if ($groupEnd > $groupStart) {
                    $sheet->mergeCells("{$column}{$groupStart}:{$column}{$groupEnd}");
                    $sheet->getStyle("{$column}{$groupStart}:{$column}{$groupEnd}")
                        ->getAlignment()
                        ->setVertical(Alignment::VERTICAL_TOP);
                }

                $groupStart = $row;
                $previousKey = $currentKey;
            }
        }
    }

    /**
     * @param  array<string, array<int, string>>  $values
     * @param  array<int, string>  $parentColumns
     */
    private function rowGroupKey(array $values, string $column, array $parentColumns, int $row): string
    {
        $parts = [];

        foreach ($parentColumns as $parentColumn) {
            $parts[] = $values[$parentColumn][$row] ?? '';
        }

        $parts[] = $values[$column][$row] ?? '';

        return implode('|', $parts);
    }
}
