<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Apr 2024 17:38:48 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\SupplyChain\Supplier\Search\SupplierRecordSearch;
use App\Models\SupplyChain\Supplier;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateSupplierUniversalSearch
{
    use asAction;

    public string $commandSignature = 'suppliers:search';

    public function handle(Supplier $supplier): void
    {
        SupplierRecordSearch::run($supplier);
    }

    public function asCommand(Command $command): int
    {
        $command->withProgressBar(Supplier::all(), function (Supplier $supplier) {
            $this->handle($supplier);
        });
        return 0;
    }
}
