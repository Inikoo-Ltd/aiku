<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Helpers\Barcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectBarcodeLink extends GrpAction
{
    public function handle(Barcode $barcode): RedirectResponse
    {
        return Redirect::to(route('grp.trade_units.barcodes.show', [$barcode->slug]));
    }

    public function asController(Barcode $barcode, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($barcode);
    }
}
