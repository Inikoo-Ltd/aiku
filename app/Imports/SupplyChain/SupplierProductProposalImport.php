<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-18 08:52
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Imports\SupplyChain;

use App\Actions\SupplyChain\SupplierSubmission\ImportSupplierPortalProductProposals;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithLimit;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Reads the first sheet of a supplier's filled product template and nothing else: the heading row plus one row more
 * than the most products accepted in a file, so a huge sheet is never loaded to be counted. Formulas are calculated,
 * so a cell shows its result and never the formula text.
 */
class SupplierProductProposalImport implements ToArray, WithCalculatedFormulas, WithLimit, WithMultipleSheets
{
    /** @var list<list<mixed>> */
    public array $rows = [];

    /**
     * @param  list<list<mixed>>  $array
     */
    public function array(array $array): void
    {
        $this->rows = $array;
    }

    public function limit(): int
    {
        return ImportSupplierPortalProductProposals::MAX_ROWS + 2;
    }

    /**
     * @return array<int, $this>
     */
    public function sheets(): array
    {
        return [0 => $this];
    }
}
