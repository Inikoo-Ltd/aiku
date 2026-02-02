<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Feb 2026 13:00:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Packing;

use App\Actions\OrgAction;
use App\Models\Dispatching\Packing;
use Lorisleiva\Actions\ActionRequest;

class DeletePacking extends OrgAction
{
    public function handle(Packing $packing): bool
    {
        $packing->delete();

        return true;
    }

    public function asController(Packing $packing, ActionRequest $request): void
    {
        $this->initialisationFromShop($packing->shop, $request);

        $this->handle($packing);
    }

    public function action(Packing $packing): bool
    {
        $this->initialisationFromShop($packing->shop, []);

        return $this->handle($packing);
    }
}
