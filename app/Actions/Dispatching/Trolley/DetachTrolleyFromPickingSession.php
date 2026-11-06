<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 20:27:20 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\Trolley;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithDispatchingEditAuthorisation;
use App\Models\Dispatching\Trolley;
use App\Models\Inventory\PickingSession;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DetachTrolleyFromPickingSession extends OrgAction
{
    use WithDispatchingEditAuthorisation;

    public function handle(Trolley $trolley, PickingSession $pickingSession): Trolley
    {
        if ($trolley->picking_session_id !== $pickingSession->id) {
            return $trolley;
        }

        UpdateTrolley::run($trolley, [
            'picking_session_id' => null,
        ]);

        $pickingSession->updateQuietly([
            'number_trolleys' => $pickingSession->trolleys()->count(),
        ]);

        return $trolley;
    }

    public function asController(PickingSession $pickingSession, Trolley $trolley, ActionRequest $request): Trolley
    {
        $this->initialisationFromWarehouse($pickingSession->warehouse, $request);

        return $this->handle($trolley, $pickingSession);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
