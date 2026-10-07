<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 7 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class UnblockPartnerOrgStock extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgStock $orgStock): OrgStock
    {
        return UpdateOrgStock::make()->action($orgStock, ['is_excluded_from_auto_ordering' => false]);
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $orgStock->organisation_id === $organisation->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($orgStock);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
