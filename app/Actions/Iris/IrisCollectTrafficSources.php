<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Oct 2025 11:43:46 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Iris;

use App\Actions\IrisAction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IrisCollectTrafficSources extends IrisAction
{
    use AsAction;


    public function handle(ActionRequest $request): void
    {
        //todo collect X-Traffic-Sources  and X-Original-Referer
    }


    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);
        $this->handle($request);
    }

}
