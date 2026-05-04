<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Apr 2026 16:44:31 Nepal Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Traits;

use Laravel\Scout\Scout;
use Laravel\Scout\Searchable;

trait HasSearch
{
    use Searchable;

    public function queueMakeSearchable($models): void
    {
        if ($models->isEmpty()) {
            return;
        }

        if (! config('scout.queue')) {
            $this->syncMakeSearchable($models);

            return;
        }

        dispatch(
            new Scout::$makeSearchableJob($models)
                ->delay(now()->addSeconds(1))
                ->onQueue($models->first()->syncWithSearchUsingQueue())
                ->onConnection($models->first()->syncWithSearchUsing())
        );
    }
}
