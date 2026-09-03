<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Wix;

use App\Actions\Dropshipping\Wix\Order\GetWixOrdersFromApi;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\WixUser;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GetRetinaOrdersFromWix extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(WixUser $wixUser): void
    {
        GetWixOrdersFromApi::run($wixUser);
    }

    public function asController(WixUser $wixUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($wixUser);
    }
}
