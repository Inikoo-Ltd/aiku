<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\WithAttributes;

class BreakWebsiteCache extends OrgAction
{
    public function handle(Website $website): Website
    {
        dd("break cache");
        return $website;
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

}
