<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Redirects;

use App\Actions\OrgAction;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectStockDeliveryLink extends OrgAction
{
    use WithAgentOrganisationRedirect;

    public function handle(StockDelivery $stockDelivery, ?User $user = null): RedirectResponse
    {
        $organisation = $stockDelivery->organisation;
        if ($user && !$user->authTo("procurement.$organisation->id.view")) {
            $organisation = $this->getAgentOrganisationForUser($user, $stockDelivery->agent_id) ?? $organisation;
        }

        return Redirect::to(route('grp.org.procurement.stock_deliveries.show', [$organisation->slug, $stockDelivery->slug]));
    }

    public function asController(StockDelivery $stockDelivery, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($stockDelivery, $request->user());
    }
}
