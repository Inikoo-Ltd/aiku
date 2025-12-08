<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 25 Oct 2022 10:26:55 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraSuppliers extends FetchAuroraAction
{
    use FetchSuppliersTrait;

    public string $commandSignature = 'fetch:suppliers {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new}';

    public function fetch($organisationSource, $organisationSourceId)
    {
        return $organisationSource->fetchSupplier($organisationSourceId);
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Supplier Dimension')
            ->leftJoin('Agent Supplier Bridge', 'Agent Supplier Supplier Key', 'Supplier Key')
            ->select('Supplier Key as source_id')
            ->where('aiku_ignore', 'No')
            ->orderBy('Supplier Valid From');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Supplier Dimension')
            ->leftJoin('Agent Supplier Bridge', 'Agent Supplier Supplier Key', 'Supplier Key')
            ->where('aiku_ignore', 'No');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
