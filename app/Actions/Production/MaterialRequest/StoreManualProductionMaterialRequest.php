<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest;

use App\Actions\OrgAction;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestSourceEnum;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestUnitEnum;
use App\Models\Production\Production;
use App\Models\Production\ProductionMaterialRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreManualProductionMaterialRequest extends OrgAction
{
    /**
     * Materials the factory asks for when it needs them, outside the morning check: any SKO,
     * in the unit the factory counts it in, to a drop point in the factory.
     *
     * @param array<int, array{org_stock_id: int, requested_quantity: float|null, requested_unit: string, production_drop_point_id: int, is_urgent?: bool, needed_by?: string|null, note?: string|null}> $lines
     */
    public function handle(Production $production, array $lines, ?User $user): ProductionMaterialRequest
    {
        if (!$production->factory_location_id) {
            throw ValidationException::withMessages(['lines' => __('This production has no factory location')]);
        }

        return DB::transaction(function () use ($production, $lines, $user) {
            $request = ProductionMaterialRequest::create([
                'group_id'             => $production->group_id,
                'organisation_id'      => $production->organisation_id,
                'production_id'        => $production->id,
                'warehouse_id'         => $production->factoryLocation->warehouse_id,
                'plan_date'            => now()->toDateString(),
                'source'               => ProductionMaterialRequestSourceEnum::MANUAL,
                'state'                => ProductionMaterialRequestStateEnum::SUBMITTED,
                'submitted_by_user_id' => $user?->id,
                'submitted_at'         => now(),
            ]);

            $request->items()->createMany(array_map(function (array $line) {
                $unit     = ProductionMaterialRequestUnitEnum::from($line['requested_unit']);
                $quantity = $unit === ProductionMaterialRequestUnitEnum::ALL ? null : (float) $line['requested_quantity'];

                return [
                    'org_stock_id'             => $line['org_stock_id'],
                    'production_drop_point_id' => $line['production_drop_point_id'],
                    'requested_quantity'       => $quantity,
                    'requested_unit'           => $unit,
                    'skos_to_bring'            => $unit === ProductionMaterialRequestUnitEnum::SKO ? $quantity : null,
                    'box_units'                => $unit === ProductionMaterialRequestUnitEnum::BOX ? self::boxUnits((int) $line['org_stock_id']) : null,
                    'is_urgent'                => (bool) ($line['is_urgent'] ?? false),
                    'needed_by'                => $line['needed_by'] ?? null,
                    'note'                     => $line['note'] ?? null,
                    'state'                    => ProductionMaterialRequestItemStateEnum::PENDING,
                ];
            }, $lines));

            return $request;
        });
    }

    /**
     * Units in one box as the morning check counts it: the main supplier's carton, else the SKO's pack.
     */
    public static function boxUnits(int $orgStockId): ?float
    {
        $boxUnits = DB::selectOne('select coalesce(('.GetDayMaterialsChecklist::supplierBoxUnitsSql('org_stocks.id').'), nullif(org_stocks.packed_in, 0)) as box_units from org_stocks where id = ?', [$orgStockId])?->box_units;

        return $boxUnits === null ? null : (float) $boxUnits;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return MaterialsCheckAuthorisation::canRequestMaterials($request->user(), $this->production);
    }

    public function rules(): array
    {
        return [
            'lines'                            => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.org_stock_id'             => ['required', 'integer', Rule::exists('org_stocks', 'id')->where('organisation_id', $this->organisation->id)],
            'lines.*.requested_unit'           => ['required', Rule::enum(ProductionMaterialRequestUnitEnum::class)],
            'lines.*.requested_quantity'       => ['nullable', 'required_unless:lines.*.requested_unit,all', 'numeric', 'gt:0', 'max:1000000'],
            'lines.*.production_drop_point_id' => ['required', 'integer', Rule::exists('production_drop_points', 'id')->where('production_id', $this->production->id)->whereNull('deleted_at')],
            'lines.*.is_urgent'                => ['sometimes', 'boolean'],
            'lines.*.needed_by'                => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'lines.*.note'                     => ['nullable', 'string', 'max:500'],
        ];
    }

    public function action(Production $production, array $lines, ?User $user = null): ProductionMaterialRequest
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['lines' => $lines]);

        return $this->handle($production, $this->validatedData['lines'], $user);
    }

    public function asController(Production $production, ActionRequest $request): ProductionMaterialRequest
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData['lines'], $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
