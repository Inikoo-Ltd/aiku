<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\DropPoint;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\Production\ProductionDropPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Soft deleted, so the requests already sent there still say where they went.
 */
class DeleteProductionDropPoint extends OrgAction
{
    use WithDropPointAuthorisation;

    public function handle(ProductionDropPoint $dropPoint): void
    {
        $dropPoint->delete();
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(ProductionDropPoint $dropPoint): void
    {
        $this->asAction = true;
        $this->initialisationFromProduction($dropPoint->production, []);

        $this->handle($dropPoint);
    }

    public function asController(Production $production, ProductionDropPoint $productionDropPoint, ActionRequest $request): void
    {
        abort_if($productionDropPoint->production_id !== $production->id, 404);
        $this->initialisationFromProduction($production, $request);

        $this->handle($productionDropPoint);
    }
}
