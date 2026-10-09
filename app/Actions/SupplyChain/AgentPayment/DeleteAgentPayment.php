<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\AgentPayment;

use App\Actions\OrgAction;
use App\Models\SupplyChain\AgentPayment;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteAgentPayment extends OrgAction
{
    public function handle(AgentPayment $agentPayment): AgentPayment
    {
        $agentPayment->delete();

        return $agentPayment;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, AgentPayment $agentPayment, ActionRequest $request): AgentPayment
    {
        abort_unless($organisation->agent?->id === $agentPayment->agent_id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentPayment);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
