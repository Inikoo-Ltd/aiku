<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 17:40:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PackingBench;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\Dispatching\PackingBench;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeletePackingBench extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(PackingBench $packingBench): PackingBench
    {
        $packingBench->delete();

        return $packingBench;
    }

    public function asController(PackingBench $packingBench, ActionRequest $request): PackingBench
    {
        $this->initialisationFromWarehouse($packingBench->warehouse, $request);

        return $this->handle($packingBench);
    }

    public function htmlResponse(PackingBench $packingBench): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.packing_benches.index', [
            $packingBench->organisation->slug,
            $packingBench->warehouse->slug,
        ]);
    }
}
