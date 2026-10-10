<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectPurchaseOrderLink extends OrgAction
{
    use WithAgentOrganisationRedirect;

    public function handle(PurchaseOrder $purchaseOrder, ?User $user = null): RedirectResponse
    {
        $organisation = $purchaseOrder->organisation;
        if ($user && !$user->authTo("procurement.$organisation->id.view")) {
            $organisation = $this->getAgentOrganisationForUser($user, $purchaseOrder->agent_id) ?? $organisation;
        }

        return Redirect::to(route('grp.org.procurement.purchase_orders.show', [$organisation->slug, $purchaseOrder->slug]));
    }

    public function asController(PurchaseOrder $purchaseOrder, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($purchaseOrder, $request->user());
    }
}
