<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 04:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Reviews\Review;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexReviewsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:reviews';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Review::class, $reindex, $reset);
    }


}
