<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrder\UI\IndexJobOrderPlan;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Enums\Production\ProductionMaterialRequestItem\ProductionMaterialRequestItemStateEnum;
use App\Models\Production\Production;
use App\Models\Production\ProductionMaterialRequest;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SubmitMaterialsCheck extends OrgAction
{
    /**
     * Turns what the morning check found missing into a request to the warehouse, rounded up
     * to the whole boxes the warehouse keeps. A check with nothing missing is recorded as done.
     *
     * @param array<int, float> $missingByRawMaterial missing units by raw material id
     */
    public function handle(Production $production, string $date, array $missingByRawMaterial, ?User $user): ProductionMaterialRequest
    {
        if (!$production->factory_location_id) {
            throw ValidationException::withMessages(['production' => __('This production has no factory location')]);
        }

        $checklist = GetDayMaterialsChecklist::run($production, $date)->keyBy('id');

        $items = [];
        foreach (array_filter($missingByRawMaterial, fn ($missing) => $missing > 0) as $rawMaterialId => $missingUnits) {
            $line = $checklist->get($rawMaterialId);
            if (!$line) {
                throw ValidationException::withMessages(['lines' => __('A material is no longer needed today, reload the page')]);
            }
            if (!$line->org_stock_id) {
                throw ValidationException::withMessages(['lines' => __(':code has no warehouse stock to ask for', ['code' => $line->code])]);
            }

            $items[] = [
                'raw_material_id' => $line->id,
                'org_stock_id'    => $line->org_stock_id,
                'needed_units'    => $line->needed_units,
                'missing_units'   => $missingUnits,
                'job_orders'      => explode(', ', $line->job_orders),
                'state'           => ProductionMaterialRequestItemStateEnum::PENDING,
                ...self::wholeBoxes($missingUnits, (float) $line->box_units, (float) $line->packed_in),
            ];
        }

        return DB::transaction(function () use ($production, $date, $items, $user) {
            $request = MaterialsCheckAuthorisation::checkingRequest($production, $date) ?? new ProductionMaterialRequest([
                'group_id'        => $production->group_id,
                'organisation_id' => $production->organisation_id,
                'production_id'   => $production->id,
                'warehouse_id'    => $production->factoryLocation->warehouse_id,
                'plan_date'       => $date,
            ]);

            $request->fill([
                'state'                => $items ? ProductionMaterialRequestStateEnum::SUBMITTED : ProductionMaterialRequestStateEnum::DELIVERED,
                'submitted_by_user_id' => $user?->id,
                'submitted_at'         => now(),
                'delivered_at'         => $items ? null : now(),
            ])->save();

            $request->items()->createMany($items);

            return $request;
        });
    }

    /**
     * @return array{box_units: float, boxes: int, skos_to_bring: float}
     */
    public static function wholeBoxes(float $missingUnits, float $boxUnits, float $packedIn): array
    {
        $boxUnits = $boxUnits > 0 ? $boxUnits : 1;
        $boxes    = (int) ceil(round($missingUnits / $boxUnits, 6));

        return [
            'box_units'     => $boxUnits,
            'boxes'         => $boxes,
            'skos_to_bring' => round($boxes * $boxUnits / max(1, $packedIn), 3),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return MaterialsCheckAuthorisation::canDoMaterialsCheck($request->user(), $this->production, IndexJobOrderPlan::parseDate($request->input('date'), $this->production));
    }

    public function rules(): array
    {
        return [
            'date'                    => ['required', 'date_format:Y-m-d'],
            'lines'                   => ['present', 'array'],
            'lines.*.raw_material_id' => ['required', 'integer'],
            'lines.*.missing_units'   => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @param array<int, float> $missingByRawMaterial
     */
    public function action(Production $production, string $date, array $missingByRawMaterial, ?User $user = null): ProductionMaterialRequest
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, ['date' => $date, 'lines' => []]);

        return $this->handle($production, $date, $missingByRawMaterial, $user);
    }

    public function asController(Production $production, ActionRequest $request): ProductionMaterialRequest
    {
        $this->initialisationFromProduction($production, $request);

        $missingByRawMaterial = collect($this->validatedData['lines'])
            ->mapWithKeys(fn (array $line) => [(int) $line['raw_material_id'] => (float) $line['missing_units']])
            ->all();

        return $this->handle($production, $this->validatedData['date'], $missingByRawMaterial, $request->user());
    }

    public function htmlResponse(ProductionMaterialRequest $request): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status' => 'success',
            'title'  => $request->state === ProductionMaterialRequestStateEnum::SUBMITTED
                ? __('Sent to the warehouse')
                : __('Check recorded, nothing missing'),
        ]);
    }
}
