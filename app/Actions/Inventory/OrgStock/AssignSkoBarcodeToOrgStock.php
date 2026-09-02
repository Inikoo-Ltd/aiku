<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Json\ScanSkoBarcode;
use App\Actions\OrgAction;
use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

/**
 * The label in the scanner's hand is the truth: whatever org stock carried this SKO barcode before
 * gives it up, and the target takes it, replacing any SKO barcode it had. Both writes go through
 * UpdateOrgStock so the stock and every sibling organisation follow, with history.
 */
class AssignSkoBarcodeToOrgStock extends OrgAction
{
    public function handle(OrgStock $orgStock, string $barcode): OrgStock
    {
        $barcode = trim($barcode);

        return DB::transaction(function () use ($orgStock, $barcode) {
            $holders = OrgStock::where('group_id', $orgStock->group_id)
                ->where('barcode', $barcode)
                ->where('id', '!=', $orgStock->id)
                ->where(fn ($query) => $query->whereNull('stock_id')->orWhere('stock_id', '!=', $orgStock->stock_id))
                ->get();

            foreach ($holders as $holder) {
                if ($holder->refresh()->barcode === $barcode) {
                    UpdateOrgStock::make()->action($holder, ['barcode' => null]);
                }
            }

            return UpdateOrgStock::make()->action($orgStock, ['barcode' => $barcode]);
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation));
    }

    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:54', 'regex:/^[\x20-\x7E]+$/'],
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        $orgStock = $this->handle($orgStock, $this->validatedData['barcode']);

        return [
            'barcode'    => $orgStock->barcode,
            'found'      => true,
            'matched_on' => 'sko',
            'org_stock'  => ScanSkoBarcode::card($warehouse, $orgStock),
        ];
    }
}
