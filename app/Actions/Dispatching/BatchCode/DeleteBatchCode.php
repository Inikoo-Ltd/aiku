<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Mon, 21 Apr 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\BatchCode;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateCurrentBatchCodes;
use App\Actions\OrgAction;
use App\Models\Dispatching\BatchCode;
use App\Models\Inventory\OrgStock;
use App\Models\Dispatching\Picking;
use App\Models\Inventory\OrgStockMovementBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteBatchCode extends OrgAction
{
    public function handle(BatchCode $batchCode): BatchCode
    {
        $orgStockId = $batchCode->org_stock_id;

        if (OrgStockMovementBatch::where('batch_code_id', $batchCode->id)->exists()) {
            throw ValidationException::withMessages([
                'batch_code' => __('This batch has stock recorded against it and cannot be deleted'),
            ]);
        }

        $batchCode->deliveryNoteItems()->update(['batch_code_id' => null]);
        Picking::where('batch_code_id', $batchCode->id)->update(['batch_code_id' => null]);
        $batchCode->delete();

        if ($orgStockId) {
            OrgStockHydrateCurrentBatchCodes::run(OrgStock::find($orgStockId));
        }

        return $batchCode;
    }

    public function asController(BatchCode $batchCode, ActionRequest $request): BatchCode
    {
        $this->initialisation($batchCode->organisation, $request);

        return $this->handle($batchCode);
    }

    public function htmlResponse(BatchCode $batchCode): RedirectResponse
    {
        $warehouse = $batchCode->organisation->warehouses()->first();

        if ($warehouse) {
            return Redirect::route('grp.org.warehouses.show.inventory.batch_codes.index', [
                $batchCode->organisation->slug,
                $warehouse->slug,
            ]);
        }

        return Redirect::route('grp.org.dashboard.show', $batchCode->organisation->slug);
    }
}
