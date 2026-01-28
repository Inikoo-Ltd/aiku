<?php

namespace App\Actions\Inventory\PickingTrolley;

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

    public function htmlResponse(PickingTrolleyModel $pickingTrolley): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.infrastructure.picking_trolleys.index', [
            $pickingTrolley->organisation->slug,
            $pickingTrolley->warehouse->slug,
        ]);
    }
}
