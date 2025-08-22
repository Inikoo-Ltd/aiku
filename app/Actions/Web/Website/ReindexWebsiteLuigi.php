<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 22-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebsiteLuigi extends OrgAction
{
    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        ReindexWebsiteLuigiData::dispatch($website);

        UpdateWebsite::run($website, [
            'last_reindex_at' => now()
        ]);
    }

    public function asController(Website $website, ActionRequest $request): void
    {
        $this->initialisation($website->organisation, $request);
        $this->handle($website);
    }
}
