<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 28 Aug 2025 16:38:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class ReindexWebpageLuigiAsync extends OrgAction
{
    /**
     * @throws \Exception
     */
    public function handle(Webpage $webpage): void
    {
        ReindexWebpageLuigi::dispatch($webpage);

    }

    /**
     * @throws \Exception
     */
    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisation($webpage->organisation, $request);
        $this->handle($webpage);
    }
}
