<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Barcode;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The group pool is one first come, first served list. The row is locked and skipped by a
 * concurrent request, so two people pressing the button at once get different numbers.
 */
class AssignNextBarcodeToTradeUnit extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(TradeUnit $tradeUnit): Barcode
    {
        if (filled($tradeUnit->barcode)) {
            throw ValidationException::withMessages(['barcode' => __('This trade unit already has a barcode')]);
        }

        return DB::transaction(function () use ($tradeUnit) {
            $barcode = Barcode::where('group_id', $tradeUnit->group_id)
                ->free()
                ->orderBy('number')
                ->lock('for update skip locked')
                ->first();

            if (!$barcode) {
                throw ValidationException::withMessages(['barcode' => __('The barcode pool has no free barcodes left')]);
            }

            return SyncBarcodeToTradeUnit::make()->action($barcode, $tradeUnit);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('goods.edit');
    }

    /**
     * @throws \Throwable
     */
    public function asController(TradeUnit $tradeUnit, ActionRequest $request): Barcode
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        return $this->handle($tradeUnit);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(TradeUnit $tradeUnit): Barcode
    {
        $this->asAction = true;
        $this->initialisationFromGroup($tradeUnit->group, []);

        return $this->handle($tradeUnit);
    }
}
