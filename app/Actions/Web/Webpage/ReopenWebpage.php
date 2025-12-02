<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 11:11:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\Traits\WithWebpageHydrators;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class ReopenWebpage extends OrgAction
{
    use WithActionUpdate;
    use WithWebpageHydrators;


    public function handle(Webpage $webpage): Webpage
    {
        $webpage->update(
            [
                'state' => WebpageStateEnum::LIVE,
            ]
        );


        $webpage->redirectedTo->delete();


        $this->dispatchWebpageHydratorsAndRefresh($webpage);

        return $webpage;
    }


    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }

}
