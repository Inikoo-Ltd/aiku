<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 11:11:03 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class SetWebpageAsOffline extends OrgAction
{
    use WithActionUpdate;


    public function handle(Webpage $webpage): Webpage
    {
        $webpage->update(
            [
                'status' => WebpageStateEnum::CLOSED
            ]
        );


        return $webpage;
    }

    public function action(Webpage $webpage): Webpage
    {
        return $this->handle($webpage);
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage);
    }


}
