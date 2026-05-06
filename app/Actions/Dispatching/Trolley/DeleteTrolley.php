<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 09 Feb 2026 14:45:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseSupervisorAuthorisation;
use App\Models\Dispatching\Trolley;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteTrolley extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithWarehouseSupervisorAuthorisation;

    private Trolley $trolley;

    public function handle(Trolley $trolley): Trolley
    {
        $trolley->delete();

        return $trolley;
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        if ($this->trolley->deliveryNotes()->exists()) {
            $validator->errors()->add('trolley', 'This trolley is still used on several delivery notes');
        }
    }

    public function asController(Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->trolley = $trolley;
        $this->initialisationFromWarehouse($trolley->warehouse, $request);

        return $this->handle($trolley);
    }

    public function htmlResponse(Trolley $trolley): RedirectResponse
    {
        return Redirect::route('grp.org.warehouses.show.dispatching.trolleys.index', [
            $trolley->organisation->slug,
            $trolley->warehouse->slug,
        ]);
    }
}
