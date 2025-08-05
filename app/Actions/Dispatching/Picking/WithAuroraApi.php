<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Aug 2025 09:35:53 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Picking;

use App\Models\Inventory\Location;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;

trait WithAuroraApi
{
    public function getAuroraObjectKey(OrgStock|Location $object): ?string
    {
        $sourceID = $object->source_id;

        if ($sourceID) {
            $sourceID = explode(':', $sourceID);

            return $sourceID[1] ?? null;
        }

        return null;
    }

    public function getApiUrl(Organisation $organisation): string
    {
        return Arr::get($organisation->source, 'url').'/api/stock';
    }

    public function getApiToken(Organisation $organisation): string
    {
        return config('app.aurora.api_keys.'.$organisation->id);
    }
}
