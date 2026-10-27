<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 27 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Labels button of a purchase order: opens the same 7-day signed PDF the AI assistant sends, with the levels
 * ticked on the button (carton when none), so the link in the address bar can go straight into the supplier's email.
 */
class OpenPurchaseOrderLabels extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentOrganisation;

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($purchaseOrder);

        $levels = array_values(array_filter(PdfPurchaseOrderLabels::LEVELS, fn (string $level) => $request->boolean($level)));

        return redirect()->away(PdfPurchaseOrderLabels::make()->url($purchaseOrder, $levels ?: ['carton']));
    }
}
