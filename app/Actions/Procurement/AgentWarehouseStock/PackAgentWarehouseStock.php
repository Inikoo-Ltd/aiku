<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\OrgAction;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent packs one line of its warehouse for the next container. Once packed, the goods receipt line it came from
 * can no longer be checked or cancelled, so goods packed before they were checked stay unchecked.
 */
class PackAgentWarehouseStock extends OrgAction
{
    public function handle(AgentWarehouseStock $agentWarehouseStock): AgentWarehouseStock
    {
        return DB::transaction(function () use ($agentWarehouseStock) {
            $stock = AgentWarehouseStock::whereKey($agentWarehouseStock->id)->lockForUpdate()->firstOrFail();

            if ($stock->state !== AgentWarehouseStockStateEnum::RECEIVED || (float) $stock->quantity <= 0) {
                throw ValidationException::withMessages(['pack' => __('This line is not waiting to be packed')]);
            }

            $stock->update(['state' => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER]);

            return $stock;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function asController(Organisation $organisation, AgentWarehouseStock $agentWarehouseStock, ActionRequest $request): AgentWarehouseStock
    {
        abort_unless($organisation->agent && $agentWarehouseStock->agent_id === $organisation->agent->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($agentWarehouseStock);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
