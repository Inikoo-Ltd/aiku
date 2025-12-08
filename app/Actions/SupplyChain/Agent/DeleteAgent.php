<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 18 Jan 2024 17:16:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Agent;

use App\Actions\GrpAction;
use App\Models\SupplyChain\Agent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteAgent extends GrpAction
{
    use AsController;
    use WithAttributes;

    public function handle(Agent $agent): Agent
    {
        $agent->delete();

        return $agent;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('procurement.edit');
    }

    public function action(Agent $agent): Agent
    {
        $this->asAction = true;

        return $this->handle($agent);
    }

    public function asController(Agent $agent, ActionRequest $request): Agent
    {
        $request->validate();

        return $this->handle($agent);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.supply-chain.agents.index');
    }
}
