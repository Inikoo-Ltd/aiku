<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 Apr 2026 20:45:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Collection\Search;

use App\Models\Catalogue\Collection;
use Illuminate\Support\Facades\Artisan;
use Lorisleiva\Actions\Concerns\AsCommand;

class ReindexCollectionSearch
{
    use AsCommand;

    public string $commandSignature = 'reindex_search:collections';

    public function asCommand(): void
    {
        Artisan::call('scout:queue-import', [
            'model'   => Collection::class,
            '--chunk' => 1000
        ]);
    }

}
