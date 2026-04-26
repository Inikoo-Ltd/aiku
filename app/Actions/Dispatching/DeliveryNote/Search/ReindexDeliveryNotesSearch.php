<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Sept 2024 23:41:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Dispatching\DeliveryNote;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexDeliveryNotesSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:delivery_notes';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(DeliveryNote::class, $reindex, $reset);
    }

}
