<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 24 Jun 2025 13:03:07 Malaysia Time, Sheffield, United Kingdom
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class BreakWebpageCache
{
    use asObject;

    public function handle(Webpage $webpage): void
    {
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_in_'.$webpage->id;
        Cache::forget($key);
        $key = config('iris.cache.webpage.prefix').'_'.$webpage->website_id.'_out_'.$webpage->id;
        Cache::forget($key);
    }
}
