<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-10 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\AgentWarehouseStock;

use App\Actions\OrgAction;
use App\Enums\Procurement\AgentWarehouseStock\AgentWarehouseStockStateEnum;
use App\Models\Procurement\AgentWarehouseStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The agent takes back a line marked packed by mistake: the row returns to received, waiting to be packed again. A row
 * from a goods receipt can only be packed whole, so when only part of it went in a container the rest shows as packed;
 * unpacking it shows that rest as received again, since what is held is worked out from the containers of its purchase
 * order line (AgentWarehouseStock::heldQuantitySql). A row whose units all went in a container is left alone.
 */
class UnpackAgentWarehouseStock extends OrgAction
{
    public string $commandSignature = 'agent_warehouse_stock:unpack {ids* : ids of the agent warehouse stock rows}';

    public function handle(AgentWarehouseStock $agentWarehouseStock): AgentWarehouseStock
    {
        return DB::transaction(function () use ($agentWarehouseStock) {
            $stock = AgentWarehouseStock::whereKey($agentWarehouseStock->id)->lockForUpdate()->firstOrFail();
            $code  = $stock->supplierProduct?->code;

            if ($stock->state === AgentWarehouseStockStateEnum::RECEIVED) {
                throw ValidationException::withMessages(['unpack' => __(':code is not packed', ['code' => $code])]);
            }

            $heldQuantity = (float) AgentWarehouseStock::whereKey($stock->id)
                ->selectRaw(AgentWarehouseStock::heldQuantitySql().' as held_quantity')
                ->value('held_quantity');

            if ($heldQuantity <= 0) {
                throw ValidationException::withMessages(['unpack' => __(':code is already in a container', ['code' => $code])]);
            }

            $stock->update(['state' => AgentWarehouseStockStateEnum::RECEIVED]);

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

    public function asCommand(Command $command): int
    {
        foreach ($command->argument('ids') as $id) {
            $stock = AgentWarehouseStock::with('supplierProduct:id,code')->findOrFail((int) $id);

            try {
                $this->handle($stock);
                $command->info("{$stock->id} {$stock->supplierProduct?->code}: unpacked");
            } catch (ValidationException $exception) {
                $command->error("{$stock->id}: ".collect($exception->errors())->flatten()->first());
            }
        }

        return 0;
    }
}
