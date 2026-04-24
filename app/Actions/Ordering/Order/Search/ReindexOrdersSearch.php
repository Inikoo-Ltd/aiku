<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 23:04:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexOrdersSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:orders';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Order::class, $reindex, $reset);
    }
}
