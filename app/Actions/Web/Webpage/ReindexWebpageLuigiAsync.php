<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 16:38:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebpageLuigiAsync extends OrgAction
{
    /**
     * @throws \Exception
     */
    public function handle(Website $website): void
    {
        ReindexWebpageLuigi::dispatch($website);

        /*UpdateWebpage::run($website, [
            'last_reindex_at' => now()
        ]);*/
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
