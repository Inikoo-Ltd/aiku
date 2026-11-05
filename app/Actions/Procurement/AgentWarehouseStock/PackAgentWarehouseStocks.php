<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-04 10:30
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
use App\Actions\Procurement\PurchaseOrder\RecordPurchaseOrderHandedOverWhenReady;

/**
 * The agent packs several lines of its warehouse at once for the next container, each whole or only some of its units,
 * as PackAgentWarehouseStock does. All or none: a line that can not be packed stops the whole batch.
 */
class PackAgentWarehouseStocks extends OrgAction
{
    /**
     * @param  array<int, array{id: int, quantity?: float|null}>  $lines
     */
    public function handle(Organisation $organisation, array $lines): int
    {
        $quantities = collect($lines)->mapWithKeys(fn (array $line) => [(int) $line['id'] => isset($line['quantity']) ? (float) $line['quantity'] : null]);

        $packed = DB::transaction(function () use ($organisation, $quantities) {
            $stocks = AgentWarehouseStock::whereIn('id', $quantities->keys())
                ->where('agent_id', $organisation->agent?->id)
                ->with('supplierProduct:id,code')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_unless($stocks->count() === $quantities->count(), 404);

            if ($stocks->doesntContain(fn (AgentWarehouseStock $stock) => $stock->state === AgentWarehouseStockStateEnum::RECEIVED)) {
                throw ValidationException::withMessages(['stocks' => __('None of these lines is waiting to be packed')]);
            }

            $stocks->each(fn (AgentWarehouseStock $stock) => PackAgentWarehouseStock::packLocked($stock, $quantities->get($stock->id)));

            return $stocks;
        });

        RecordPurchaseOrderHandedOverWhenReady::make()->forPurchaseOrderIds($packed->pluck('purchase_order_id'));

        return $packed->count();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'stocks'            => ['required', 'array', 'min:1', 'max:500'],
            'stocks.*.id'       => ['required', 'integer', 'distinct'],
            'stocks.*.quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): int
    {
        abort_unless($organisation->agent, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $this->validatedData['stocks']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
