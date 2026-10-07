<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\Procurement\OrgSupplier;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectSupplierLink extends OrgAction
{
    public function handle(Supplier $supplier, ?User $user = null): RedirectResponse
    {
        if ($user && !$user->authTo('supply-chain.view')) {
            $orgSupplier = $supplier->orgSuppliers()->with('organisation')->get()
                ->first(fn (OrgSupplier $orgSupplier) => $user->authTo("procurement.{$orgSupplier->organisation_id}.view"));

            if ($orgSupplier) {
                return Redirect::to(route('grp.org.procurement.org_suppliers.show', [$orgSupplier->organisation->slug, $orgSupplier->slug]));
            }
        }

        if ($supplier->agent_id) {
            return Redirect::to(route('grp.supply-chain.agents.show.suppliers.show', [$supplier->agent->slug, $supplier->slug]));
        }

        return Redirect::to(route('grp.supply-chain.suppliers.show', [$supplier->slug]));
    }

    public function asController(Supplier $supplier, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($supplier, $request->user());
    }
}
