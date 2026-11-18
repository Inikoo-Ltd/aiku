<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\ProductionMaterials;

use App\Actions\OrgAction;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\Production\ProductionMaterialRequestItem;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetProductionMaterialRequestItemShort extends OrgAction
{
    /**
     * The warehouse cannot bring (the rest of) a material: the factory sees it on its check
     * straight away instead of waiting for it.
     */
    public function handle(ProductionMaterialRequestItem $item, ?string $reason, ?User $user): ProductionMaterialRequestItem
    {
        if ($item->state !== ProductionMaterialRequestItemStateEnum::PENDING) {
            throw ValidationException::withMessages(['item' => __('This material is no longer waiting to be brought')]);
        }

        $item->update([
            'state'                => ProductionMaterialRequestItemStateEnum::SHORT,
            'short_reason'         => $reason,
            'short_at'             => now(),
            'delivered_by_user_id' => $user?->id ?? $item->delivered_by_user_id,
        ]);

        DeliverProductionMaterialRequestItem::settleRequest($item);

        return $item->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->warehouse->canBeWorkedInBy($request->user());
    }

    public function rules(): array
    {
        return [
            'reason' => [$this->asAction ? 'nullable' : 'required', 'string', 'max:255'],
        ];
    }

    public function action(ProductionMaterialRequestItem $item, ?string $reason = null, ?User $user = null): ProductionMaterialRequestItem
    {
        $this->asAction = true;
        $this->initialisationFromWarehouse($item->request->warehouse, ['reason' => $reason]);

        return $this->handle($item, $reason, $user);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ProductionMaterialRequestItem $productionMaterialRequestItem, ActionRequest $request): ProductionMaterialRequestItem
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        if ($productionMaterialRequestItem->request->warehouse_id !== $warehouse->id) {
            abort(404);
        }

        return $this->handle($productionMaterialRequestItem, $this->validatedData['reason'], $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => __("Production told the warehouse can't bring it"),
        ]);
    }
}
