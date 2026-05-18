<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 24 Oct 2025 08:22:24 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\Luigi;

use App\Actions\OrgAction;
use App\Actions\Web\Website\UpdateWebsite;
use App\Models\Web\Website;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebsiteLuigiAsync extends OrgAction
{
    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        Log::info("Running ReindexWebsiteLuigiAsync");
        ReindexWebsiteLuigi::dispatch($website)->delay(60);

        UpdateWebsite::run($website, [
            'last_reindex_at' => now()
        ]);
    }

    /**
     * @throws \Exception
     */
    public function asController(Website $website, ActionRequest $request): void
    {
        $this->initialisation($website->organisation, $request);
        $this->handle($website);
    }
}
