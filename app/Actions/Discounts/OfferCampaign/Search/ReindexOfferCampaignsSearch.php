<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\OfferCampaign\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexOfferCampaignsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:offer_campaigns';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(OfferCampaign::class, $reindex, $reset);
    }


}
