<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Aug 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Goods\Barcode\AssignNextBarcodeToTradeUnit;
use App\Actions\Goods\Barcode\StoreBarcode;
use App\Actions\Goods\Barcode\SyncBarcodeToTradeUnit;
use App\Actions\OrgAction;
use App\Enums\Helpers\Barcode\BarcodeStatusEnum;
use App\Enums\Helpers\Barcode\BarcodeTypeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Barcode;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Actions\Traits\WithActionUpdate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The unit EAN a supervisor edits here belongs to the trade unit, not the org stock: it cascades to
 * the product barcode shown on the website and every sales channel, so it is gated to org stocks
 * made of exactly one trade unit and to supervisors only.
 */
class UpdateOrgStockUnitBarcode extends OrgAction
{
    use WithActionUpdate;

    private OrgStock $orgStock;

    public function handle(OrgStock $orgStock, array $modelData): OrgStock
    {
        if (!$orgStock->is_single_trade_unit || $orgStock->tradeUnits->count() !== 1) {
            throw ValidationException::withMessages([
                'unit_barcode' => __('This SKO does not have exactly one trade unit.'),
            ]);
        }

        $tradeUnit = $orgStock->tradeUnits->first();

        if ($modelData['from_pool'] ?? false) {
            AssignNextBarcodeToTradeUnit::make()->action($tradeUnit);

            return $orgStock->refresh();
        }

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
            if ($holder && $holder->id !== $tradeUnit->id && !$this->isAbandonedAuroraCopy($holder)) {
                throw ValidationException::withMessages([
                    'unit_barcode'            => __('Barcode :number already belongs to a different item, :item. Each item needs its own barcode.', [
                        'number' => $number,
                        'item'   => $holder->name ? $holder->code.' ('.$holder->name.')' : $holder->code,
                    ]),
                    'unit_barcode_holder_url' => route('grp.trade_units.units.show', ['tradeUnit' => $holder->slug]),
                ]);
            }
        } else {
            $barcode = StoreBarcode::make()->action($tradeUnit->group, [
                'number' => $number,
                'type'   => BarcodeTypeEnum::EAN->value,
                'status' => BarcodeStatusEnum::USED->value,
                'data'   => ['external' => true],
            ]);
        }

        SyncBarcodeToTradeUnit::make()->action($barcode, $tradeUnit);

        return $orgStock->refresh();
    }

    private function isAbandonedAuroraCopy(TradeUnit $tradeUnit): bool
    {
        return $tradeUnit->source_id !== null
            && !DB::table('model_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->exists()
            && !DB::table('invoice_transaction_has_trade_units')->where('trade_unit_id', $tradeUnit->id)->exists();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("supervisor-stocks.{$this->warehouse->id}");
    }

    public function rules(): array
    {
        return [
            'unit_barcode' => ['sometimes', 'nullable', 'string', 'regex:/^\d{8,14}$/'],
            'from_pool'    => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->orgStock = $orgStock;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($orgStock, $this->validatedData);
    }
}
