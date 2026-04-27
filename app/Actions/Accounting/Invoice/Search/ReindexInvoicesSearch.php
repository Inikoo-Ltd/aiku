<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 01:46:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Accounting\Invoice;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexInvoicesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:invoices';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Invoice::class, $reindex, $reset);
    }

}
