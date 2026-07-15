<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Tag\Search;

use App\Actions\Traits\WithScoutReindex;
use App\Models\Helpers\Tag;
use Lorisleiva\Actions\Concerns\AsAction;

class ReindexTagsSearch
{
    use AsAction;
    use WithScoutReindex;

    public string $commandSignature = 'reindex_search:tags';

    public function handle(bool $reindex = true, bool $reset = false): void
    {
        $this->runScoutReindex(Tag::class, $reindex, $reset);
    }


}
