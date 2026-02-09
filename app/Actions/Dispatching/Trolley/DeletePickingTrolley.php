<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:45:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Inventory\PickingTrolley as PickingTrolleyModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeletePickingTrolley extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;

    public function handle(PickingTrolleyModel $pickingTrolley): PickingTrolleyModel
    {
        $pickingTrolley->delete();

        return $pickingTrolley;
    }

    public function asController(PickingTrolleyModel $pickingTrolley, ActionRequest $request): PickingTrolleyModel
    {
        $this->initialisationFromWarehouse($pickingTrolley->warehouse, $request);

        return $this->handle($pickingTrolley);
    }

    public function htmlResponse(PickingTrolleyModel $trolley): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.trolleys.index', [
            $trolley->organisation->slug,
            $trolley->warehouse->slug,
        ]);
    }
}
