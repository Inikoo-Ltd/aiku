<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Billables\ShippingZoneSchema;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexShippingZoneSchemasSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:shipping_zone_schemas';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(ShippingZoneSchema::class, $reindex, $reset);
    }


}
