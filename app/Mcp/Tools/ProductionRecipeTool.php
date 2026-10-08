<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\Production\Artefact\SetArtefactsRecipe;
use App\Enums\SysAdmin\Authorisation\ProductionPermissionsEnum;
use App\Enums\SysAdmin\McpChange\McpChangeTypeEnum;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use App\Models\Production\ManufactureTask;
use App\Models\Production\Production;
use App\Models\Production\RawMaterial;
use App\Models\Production\RecipeStepRawMaterial;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

/**
 * Writes through SetArtefactsRecipe, the "change many artefacts at once" action of the artefacts
 * page, so open job orders pick up the new steps exactly as they do from the UI.
 */
#[Description('Shows or replaces the recipe of artefacts: the production steps (manufacture tasks) in order, how many units of each step one artefact counts as, the target per hour of the step, and the raw materials each step uses per artefact, with their cost. Pick the artefacts by code (artefacts), by whole families (families, by code or slug; discontinued artefacts are left out) or both, and leave some out with except; up to 200 per call. Without steps it shows the recipes and the materials cost of each artefact. With steps every listed artefact gets exactly those steps: steps it has that are not in the list are removed with their raw materials, so pass the whole recipe, not only additions. units_per_artefact is how much of the step one artefact counts as (e.g. 0.1667 when packing is counted in boxes of six tins). When similar artefacts share the steps but differ in an ingredient (e.g. each flavour has its own oil), give the shared raw materials in raw_materials and, for the artefacts that differ, their complete list for that step in artefact_raw_materials. Open job orders that are not received yet pick up the new steps. Create missing tasks or raw materials first with production-records-tool. Show the user the steps per artefact and write only after they confirmed in their own words, passing their request text. Only for users enrolled to set up production through their assistant.')]
class ProductionRecipeTool extends Tool
{
    use WithMcpPermissions;
    use WithMcpChangeLog;
    use WithMcpProduction;

    public function handle(Request $request): Response
    {
        $request->validate([
            'production'                            => ['required', 'string'],
            'artefacts'                             => ['required_without:families', 'array', 'max:200'],
            'artefacts.*'                           => ['string'],
            'families'                              => ['required_without:artefacts', 'array', 'max:50'],
            'families.*'                            => ['string'],
            'except'                                => ['sometimes', 'array'],
            'except.*'                              => ['string'],
            'steps'                                 => ['sometimes', 'array', 'min:1', 'max:30'],
            'steps.*.task'                          => ['required', 'string'],
            'steps.*.position'                      => ['sometimes', 'integer', 'min:1'],
            'steps.*.units_per_artefact'            => ['required', 'numeric', 'gt:0'],
            'steps.*.target_per_hour'               => ['present', 'nullable', 'numeric', 'gt:0'],
            'steps.*.raw_materials'                 => ['sometimes', 'array'],
            'steps.*.raw_materials.*.code'          => ['required', 'string'],
            'steps.*.raw_materials.*.quantity'      => ['required', 'numeric', 'gt:0'],
            'steps.*.artefact_raw_materials'                          => ['sometimes', 'array'],
            'steps.*.artefact_raw_materials.*.artefact'               => ['required', 'string'],
            'steps.*.artefact_raw_materials.*.raw_materials'          => ['present', 'array'],
            'steps.*.artefact_raw_materials.*.raw_materials.*.code'   => ['required', 'string'],
            'steps.*.artefact_raw_materials.*.raw_materials.*.quantity' => ['required', 'numeric', 'gt:0'],
            'request_text'                          => ['required_with:steps', 'string', 'max:4000'],
        ]);

        $production = $this->resolveProduction($request);
        if ($production instanceof Response) {
            return $production;
        }

        $codes     = collect($request->get('artefacts', []))->map(fn ($code) => strtolower(trim($code)))->unique();
        $artefacts = Artefact::where('production_id', $production->id)
            ->whereIn(DB::raw('lower(code)'), $codes->all())
            ->get();

        $missing = $codes->diff($artefacts->map(fn (Artefact $artefact) => strtolower($artefact->code)));
        if ($missing->isNotEmpty()) {
            return Response::error("Unknown artefacts in {$production->code}: ".$missing->implode(', ').'. Nothing was changed.');
        }

        if ($request->has('families')) {
            $families = $this->families($production, $request->get('families'));
            if ($families instanceof Response) {
                return $families;
            }
            $artefacts = $artefacts->merge(Artefact::whereIn('artefact_family_id', $families->pluck('id'))->where('state', '!=', ArtefactStateEnum::DISCONTINUED)->get());
        }

        $except    = collect($request->get('except', []))->map(fn ($code) => strtolower(trim($code)));
        $artefacts = $artefacts->unique('id')
            ->reject(fn (Artefact $artefact) => $except->contains(strtolower($artefact->code)))
            ->sortBy('code')
            ->values();

        if ($artefacts->isEmpty()) {
            return Response::error('No artefacts left to work on. Nothing was changed.');
        }
        if ($artefacts->count() > 200) {
            return Response::error("That is {$artefacts->count()} artefacts; do at most 200 per call, e.g. one family at a time. Nothing was changed.");
        }

        if (!$request->has('steps')) {
            return Response::json($this->summary($production, $artefacts));
        }

        if (!$this->canInProduction($request, $production, ProductionPermissionsEnum::PRODUCTION_RD_EDIT)) {
            return $this->cannotEditError($production);
        }

        try {
            $steps = $this->steps($production, collect($request->get('steps')), $artefacts);

            $this->recordChange(
                $request,
                McpChangeTypeEnum::PRODUCTION_RECIPE,
                "Recipe of {$artefacts->pluck('code')->implode(', ')} in {$production->code}",
                ['production_id' => $production->id, 'artefact_ids' => $artefacts->pluck('id')->all()],
                fn () => SetArtefactsRecipe::make()->action($production, [
                    'artefacts' => $artefacts->pluck('id')->all(),
                    'steps'     => $steps,
                ]),
                ['production' => $production->code]
            );
        } catch (ValidationException $exception) {
            return $this->validationError($exception);
        }

        return Response::json([
            'changed'       => (bool) $this->mcpChange,
            'change_log_id' => $this->mcpChange?->id,
            ...$this->summary($production, $artefacts),
        ]);
    }

    /**
     * A family is matched by slug or code. Codes are not unique (awa has two ACLB families, one of
     * them the testers), so an ambiguous code is refused with the slugs to choose from.
     */
    private function families(Production $production, array $identifiers): Collection|Response
    {
        $families = collect();
        foreach ($identifiers as $identifier) {
            $matches = ArtefactFamily::where('production_id', $production->id)
                ->where(fn ($query) => $query->whereRaw('lower(slug) = ?', [strtolower($identifier)])->orWhereRaw('lower(code) = ?', [strtolower($identifier)]))
                ->withCount(['artefacts' => fn ($query) => $query->where('state', '!=', ArtefactStateEnum::DISCONTINUED)])
                ->get();

            if ($matches->isEmpty()) {
                return Response::error("There is no artefact family {$identifier} in {$production->code}. Families: ".ArtefactFamily::where('production_id', $production->id)->orderBy('code')->pluck('code')->unique()->implode(', ').'. Nothing was changed.');
            }

            if ($matches->count() > 1) {
                return Response::error("More than one family is coded {$identifier} in {$production->code}; pass the slug of the one you mean: ".$matches->map(fn (ArtefactFamily $family) => $family->slug.' ('.$family->name.', '.$family->artefacts_count.' artefacts starting '.$family->artefacts()->orderBy('code')->value('code').')')->implode('; ').'. Nothing was changed.');
            }

            $families->push($matches->first());
        }

        return $families;
    }

    private function steps(Production $production, Collection $steps, Collection $artefacts): array
    {
        $tasks = ManufactureTask::where('production_id', $production->id)
            ->whereIn(DB::raw('lower(code)'), $steps->map(fn (array $step) => strtolower($step['task']))->all())
            ->get()
            ->keyBy(fn (ManufactureTask $task) => strtolower($task->code));

        $perArtefact      = $steps->pluck('artefact_raw_materials')->flatten(1)->filter();
        $rawMaterialCodes = $steps->pluck('raw_materials')->flatten(1)->filter()
            ->merge($perArtefact->pluck('raw_materials')->flatten(1))
            ->map(fn (array $rawMaterial) => strtolower($rawMaterial['code']));
        $artefactIds      = $artefacts->keyBy(fn (Artefact $artefact) => strtolower($artefact->code))->map->id;
        $rawMaterials     = RawMaterial::where('organisation_id', $production->organisation_id)
            ->whereIn(DB::raw('lower(code)'), $rawMaterialCodes->all())
            ->get()
            ->keyBy(fn (RawMaterial $rawMaterial) => strtolower($rawMaterial->code));

        $unknown = [
            ...$steps->pluck('task')->reject(fn ($code) => $tasks->has(strtolower($code)))->map(fn ($code) => "task {$code}"),
            ...$rawMaterialCodes->unique()->reject(fn ($code) => $rawMaterials->has($code))->map(fn ($code) => "raw material {$code}"),
        ];
        $notSelected = $perArtefact->pluck('artefact')->reject(fn ($code) => $artefactIds->has(strtolower($code)));
        if ($notSelected->isNotEmpty()) {
            throw ValidationException::withMessages(['steps' => 'artefact_raw_materials name artefacts that are not being changed: '.$notSelected->unique()->implode(', ').'.']);
        }

        $hasRepeat = fn ($rawMaterials) => collect($rawMaterials)->countBy(fn (array $rawMaterial) => strtolower($rawMaterial['code']))->max() > 1;
        $repeated  = $steps->filter(fn (array $step) => $hasRepeat($step['raw_materials'] ?? [])
            || collect($step['artefact_raw_materials'] ?? [])->contains(fn (array $override) => $hasRepeat($override['raw_materials'])))->pluck('task');
        if ($repeated->isNotEmpty()) {
            throw ValidationException::withMessages(['steps' => 'A raw material is listed twice in step '.$repeated->implode(', ').'; give it once with the quantities added up.']);
        }

        if ($unknown) {
            throw ValidationException::withMessages(['steps' => 'Not found in '.$production->code.': '.implode(', ', $unknown).'. Create them with production-records-tool first.']);
        }

        $lines = fn (array $list) => collect($list)->map(fn (array $rawMaterial) => [
            'raw_material_id'   => $rawMaterials[strtolower($rawMaterial['code'])]->id,
            'quantity_per_unit' => $rawMaterial['quantity'],
        ])->all();

        return $steps->values()->map(fn (array $step, int $index) => [
            'manufacture_task_id'    => $tasks[strtolower($step['task'])]->id,
            'position'               => $step['position'] ?? $index + 1,
            'units_per_artefact'     => $step['units_per_artefact'],
            'standard_rate'          => $step['target_per_hour'],
            'raw_materials'          => $lines($step['raw_materials'] ?? []),
            'artefact_raw_materials' => collect($step['artefact_raw_materials'] ?? [])->map(fn (array $override) => [
                'artefact_id'   => $artefactIds[strtolower($override['artefact'])],
                'raw_materials' => $lines($override['raw_materials']),
            ])->all(),
        ])->all();
    }

    private function summary(Production $production, Collection $artefacts): array
    {
        $artefacts->load(['manufactureTasks', 'artefactFamily:id,code']);
        $rawMaterialsByStep = RecipeStepRawMaterial::whereIn('artefact_manufacture_task_id', $artefacts->pluck('manufactureTasks')->flatten()->pluck('pivot.id'))
            ->with('rawMaterial')
            ->get()
            ->groupBy('artefact_manufacture_task_id');

        return [
            'production' => $production->code,
            'currency'   => $production->organisation->currency->code,
            'artefacts'  => $artefacts->map(function (Artefact $artefact) use ($rawMaterialsByStep) {
                $steps = $artefact->manufactureTasks->map(function (ManufactureTask $task) use ($rawMaterialsByStep) {
                    $rawMaterials = $rawMaterialsByStep->get($task->pivot->id, collect())->map(fn (RecipeStepRawMaterial $line) => [
                        'code'        => $line->rawMaterial->code,
                        'description' => $line->rawMaterial->description,
                        'unit'        => $line->rawMaterial->unit,
                        'quantity'    => (float) $line->quantity_per_unit,
                        'unit_cost'   => (float) $line->rawMaterial->unit_cost,
                        'cost'        => round($line->quantity_per_unit * $line->rawMaterial->unit_cost, 4),
                    ]);

                    return [
                        'position'           => $task->pivot->position,
                        'task'               => $task->code,
                        'task_name'          => $task->name,
                        'units_per_artefact' => (float) $task->pivot->units_per_artefact,
                        'target_per_hour'    => $task->pivot->standard_rate === null ? null : (float) $task->pivot->standard_rate,
                        'raw_materials'      => $rawMaterials->all(),
                    ];
                });

                return [
                    'code'           => $artefact->code,
                    'name'           => $artefact->name,
                    'state'          => $artefact->state->value,
                    'family'         => $artefact->artefactFamily?->code,
                    'steps'          => $steps->all(),
                    'materials_cost' => round($steps->pluck('raw_materials')->flatten(1)->sum('cost'), 4),
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'production'   => $schema->string()->description('Production slug or code, e.g. awa')->required(),
            'artefacts'    => $schema->array()->items($schema->string())->description('Artefact codes; with steps they all get the same recipe'),
            'families'     => $schema->array()->items($schema->string())->description('Artefact family codes or slugs: every artefact in them that is not discontinued'),
            'except'       => $schema->array()->items($schema->string())->description('Artefact codes to leave out of the selection'),
            'steps'        => $schema->array()->items($schema->object([
                'task'               => $schema->string()->description('Manufacture task code, e.g. POUR')->required(),
                'position'           => $schema->integer()->description('Order of the step, defaults to its place in the list'),
                'units_per_artefact' => $schema->number()->description('How much of this step one artefact counts as (1 for one tin, 0.1667 when counted in boxes of six). Always send it, copy the current value when not changing it')->required(),
                'target_per_hour'    => $schema->number()->description('Units of this step one person should do per hour; null when there is no target. Always send it, copy the current value when not changing it')->required(),
                'raw_materials'      => $schema->array()->items($schema->object([
                    'code'     => $schema->string()->description('Raw material code')->required(),
                    'quantity' => $schema->number()->description('Quantity used per artefact, in the raw material unit')->required(),
                ]))->description('Raw materials this step uses, per artefact'),
                'artefact_raw_materials' => $schema->array()->items($schema->object([
                    'artefact'      => $schema->string()->description('Artefact code; must be one of the artefacts being changed')->required(),
                    'raw_materials' => $schema->array()->items($schema->object([
                        'code'     => $schema->string()->required(),
                        'quantity' => $schema->number()->required(),
                    ]))->description('The complete raw material list of this step for this artefact, replacing raw_materials for it')->required(),
                ]))->description('Only for artefacts whose raw materials in this step differ from raw_materials'),
            ]))->description('The whole new recipe. Omit to only show the current recipes'),
            'request_text' => $schema->string()->description('The user\'s request, verbatim; required when writing'),
        ];
    }
}
