<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\Goods\TradeUnit\UpdateTradeUnit;
use App\Actions\OrgAction;
use App\Enums\Goods\Packaging\PackagingBrandOwnershipEnum;
use App\Enums\Goods\Packaging\PackagingFamilySourceEnum;
use App\Enums\Goods\Packaging\PackagingLevelEnum;
use App\Enums\Goods\Packaging\PackagingMaterialCategoryEnum;
use App\Models\Goods\PackagingComponent;
use App\Models\Goods\PackagingFamily;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Marks an SKO as shipment packaging (a carton, mailer or tape the warehouse uses to send parcels) and records what
 * one unit of it weighs, so the packaging EPR return can report it as it is used.
 */
class SetOrgStockShipmentPackaging extends OrgAction
{
    public function handle(OrgStock $orgStock, PackagingMaterialCategoryEnum $material, float $weightG): OrgStock
    {
        $tradeUnit = $orgStock->tradeUnits()->first();
        if (!$tradeUnit) {
            throw ValidationException::withMessages(['code' => __('This SKO has no trade unit to hold its weight.')]);
        }

        return DB::transaction(function () use ($orgStock, $tradeUnit, $material, $weightG) {
            $family = PackagingFamily::firstOrCreate(
                ['group_id' => $tradeUnit->group_id, 'signature' => sha1('shipment|'.$tradeUnit->id)],
                ['code' => $orgStock->code, 'name' => $orgStock->name, 'status' => 'active']
            );
            $family->update(['source' => PackagingFamilySourceEnum::SUPPLIER, 'brand_ownership' => PackagingBrandOwnershipEnum::UNBRANDED]);

            $weight    = round($weightG, 3);
            $component = PackagingComponent::firstOrCreate(
                ['group_id' => $tradeUnit->group_id, 'signature' => sha1("shipment|$material->value|$weight")],
                [
                    'name'              => $material->labels()[$material->value],
                    'packaging_level'   => PackagingLevelEnum::SERVICE,
                    'material_category' => $material,
                    'weight_g'          => $weight,
                ]
            );
            $family->components()->sync([$component->id => ['quantity' => 1, 'quantity_per_unit' => 1]]);

            /** @var TradeUnit $tradeUnit */
            if ($tradeUnit->packaging_family_id !== $family->id) {
                UpdateTradeUnit::make()->action($tradeUnit, ['packaging_family_id' => $family->id], strict: false);
            }
            $orgStock->update(['is_shipment_packaging' => true]);

            return $orgStock;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function rules(): array
    {
        return [
            'code'              => ['required', 'string'],
            'material_category' => ['required', Rule::enum(PackagingMaterialCategoryEnum::class)],
            'weight_g'          => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): OrgStock
    {
        $this->initialisation($organisation, $request);

        $orgStock = OrgStock::where('organisation_id', $organisation->id)
            ->whereRaw('lower(code) = ?', [mb_strtolower(trim($this->validatedData['code']))])
            ->first();
        if (!$orgStock) {
            throw ValidationException::withMessages(['code' => __('No SKO with this code.')]);
        }

        return $this->handle($orgStock, PackagingMaterialCategoryEnum::from($this->validatedData['material_category']), (float)$this->validatedData['weight_g']);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
