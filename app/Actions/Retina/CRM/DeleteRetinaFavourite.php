<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 11:16:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM;

use App\Actions\CRM\Favourite\UnFavourite;
use App\Actions\RetinaAction;
use App\Models\CRM\Favourite;
use Lorisleiva\Actions\ActionRequest;

class DeleteRetinaFavourite extends RetinaAction
{
    public function handle(Favourite $favourite): void
    {
        UnFavourite::make()->action($favourite, []);
    }

    public function asController(Favourite $favourite, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($favourite);
    }

}
