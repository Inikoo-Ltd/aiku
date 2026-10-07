<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 09:35:53 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithAuroraApi
{
    public function getApiUrl(Organisation $organisation): string
    {
        return Arr::get($organisation->source, 'url').'/api/stock';
    }

    public function getApiToken(Organisation $organisation): ?string
    {
        return config('app.aurora.api_keys.'.$organisation->id);
    }
}
