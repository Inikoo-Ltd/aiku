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

/**
 * The agent packs several lines of its warehouse at once for the next container, each as PackAgentWarehouseStock
 * does. Lines already packed are left as they are, so a second click changes nothing.
 */
class PackAgentWarehouseStocks extends OrgAction
{
    /**
     * @param  array<int, int>  $stockIds
     */
    public function handle(Organisation $organisation, array $stockIds): int
    {
        return DB::transaction(function () use ($organisation, $stockIds) {
            $stocks = AgentWarehouseStock::whereIn('id', $stockIds)
                ->where('agent_id', $organisation->agent?->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_unless($stocks->count() === count(array_unique($stockIds)), 404);

            $packable = $stocks->filter(fn (AgentWarehouseStock $stock) => $stock->state === AgentWarehouseStockStateEnum::RECEIVED && (float) $stock->quantity > 0);

            if ($packable->isEmpty()) {
                throw ValidationException::withMessages(['stocks' => __('None of these lines is waiting to be packed')]);
            }

            AgentWarehouseStock::whereIn('id', $packable->pluck('id'))->update(['state' => AgentWarehouseStockStateEnum::PACKED_FOR_NEXT_CONTAINER]);

            return $packable->count();
        });
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
            'stocks'   => ['required', 'array', 'min:1', 'max:500'],
            'stocks.*' => ['integer', 'distinct'],
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
