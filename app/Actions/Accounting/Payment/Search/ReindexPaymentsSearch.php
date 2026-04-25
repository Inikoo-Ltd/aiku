<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-11-2024, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Accounting\Payment\Search;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithScoutReindex;
use App\Models\Accounting\Payment;
use App\Models\Ordering\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexPaymentsSearch extends HydrateModel
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:payments';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Payment::class, $reindex, $reset);
    }
}
