<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 04 Aug 2026 12:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\MasterAsset;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The "copy master to all children" button on the composition page: pushes the
 * master's trade unit composition (which re-derives warehouse picking) and its
 * prices/rrps to every child product that has not opted out, then re-evaluates
 * the mismatch flags so the anomalies block reflects the fix immediately.
 */
class FixMasterAssetAnomaliesFromMaster extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithMasterAssetCascadeDispatch;

    public function handle(MasterAsset $masterProduct): MasterAsset
    {
        $this->cascadeToChildren($masterProduct, true, true);

        return $masterProduct;
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): MasterAsset
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        return $this->handle($masterAsset);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
