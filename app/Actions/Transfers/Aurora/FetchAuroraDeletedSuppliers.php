<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Feb 2023 12:18:36 Malaysia Time, Bali Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraDeletedSuppliers extends FetchAuroraAction
{
    use FetchSuppliersTrait;

    public string $commandSignature = 'fetch:deleted_suppliers {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function fetch($organisationSource, $organisationSourceId)
    {
        return $organisationSource->fetchDeletedSupplier($organisationSourceId);
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Supplier Deleted Dimension')
            ->select('Supplier Deleted Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Supplier Deleted Dimension')->count();
    }
}
