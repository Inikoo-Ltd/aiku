<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Goods\Barcode\SyncBarcodeToTradeUnit;
use App\Actions\OrgAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Models\Helpers\Barcode;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The unit EAN a supervisor edits here belongs to the trade unit, not the org stock: it cascades to
 * the product barcode shown on the website and every sales channel, so it is gated to org stocks
 * made of exactly one trade unit and to supervisors only.
 */
class UpdateOrgStockUnitBarcode extends OrgAction
{
    private OrgStock $orgStock;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        if (!$orgStock->is_single_trade_unit || $orgStock->tradeUnits->count() !== 1) {
            throw ValidationException::withMessages([
                'unit_barcode' => __('This SKO does not have exactly one trade unit.'),
            ]);
        }

        $tradeUnit = $orgStock->tradeUnits->first();
        $number    = trim((string)($modelData['unit_barcode'] ?? ''));

        if ($number === '') {
            if ($tradeUnit->barcode_id) {
                SyncBarcodeToTradeUnit::make()->action($tradeUnit->barcode()->first(), null);
            }

            return $orgStock->refresh();
        }

        $barcode = Barcode::where('group_id', $tradeUnit->group_id)->where('number', $number)->first();

        if ($barcode) {
            $holder = $barcode->tradeUnitsActive->first();
            if ($holder && $holder->id !== $tradeUnit->id) {
                throw ValidationException::withMessages([
                    'unit_barcode' => __('Barcode :number is already assigned to trade unit :code.', ['number' => $number, 'code' => $holder->code]),
                ]);
            }
        } else {
            $barcode = StoreBarcode::make()->action($tradeUnit->group, [
                'number' => $number,
                'type'   => BarcodeTypeEnum::EAN->value,
                'status' => BarcodeStatusEnum::USED->value,
            ]);
        }

        SyncBarcodeToTradeUnit::make()->action($barcode, $tradeUnit);

        return $orgStock->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("supervisor-stocks.{$this->warehouse->id}");
    }

    public function rules(): array
    {
        return [
            'unit_barcode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{8,14}$/'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->orgStock = $orgStock;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock, $this->validatedData);
    }
}
