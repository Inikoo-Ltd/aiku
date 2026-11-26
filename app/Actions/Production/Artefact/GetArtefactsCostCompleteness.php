<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\Production\Artefact\UI\IndexArtefacts;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * INI-567: how many active artefacts can be costed from production (recipe materials plus labour). Each artefact
 * falls in the first gap it has: no recipe, a recipe material without a cost, a step without a target per hour
 * (labour cannot be costed), else complete.
 */
class GetArtefactsCostCompleteness
{
    use AsObject;

    public const array STATUSES = ['complete', 'no_labour', 'unpriced_material', 'no_recipe'];

    public static function statusSql(): string
    {
        return "CASE
            WHEN NOT EXISTS (SELECT 1 FROM artefacts_manufacture_tasks cc_step JOIN recipe_step_raw_materials cc_line ON cc_line.artefact_manufacture_task_id = cc_step.id WHERE cc_step.artefact_id = artefacts.id) THEN 'no_recipe'
            WHEN EXISTS (SELECT 1 FROM artefacts_manufacture_tasks cc_step JOIN recipe_step_raw_materials cc_line ON cc_line.artefact_manufacture_task_id = cc_step.id JOIN raw_materials cc_material ON cc_material.id = cc_line.raw_material_id WHERE cc_step.artefact_id = artefacts.id AND (cc_material.deleted_at IS NOT NULL OR COALESCE(cc_material.unit_cost, 0) <= 0)) THEN 'unpriced_material'
            WHEN EXISTS (SELECT 1 FROM artefacts_manufacture_tasks cc_step JOIN manufacture_tasks cc_task ON cc_task.id = cc_step.manufacture_task_id WHERE cc_step.artefact_id = artefacts.id AND COALESCE(cc_step.standard_rate, cc_task.standard_rate, 0) <= 0) THEN 'no_labour'
            ELSE 'complete'
        END";
    }

    /**
     * @return array{total: int, counts: array<string, int>}
     */
    public function handle(string $column, int $id): array
    {
        $counts = Artefact::where($column, $id)
            ->where('state', ArtefactStateEnum::ACTIVE)
            ->whereNull('deleted_at')
            ->selectRaw(self::statusSql().' as cost_status, count(*) as number')
            ->groupBy('cost_status')
            ->pluck('number', 'cost_status');

        $counts = collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => (int) ($counts[$status] ?? 0)])->all();

        return ['total' => array_sum($counts), 'counts' => $counts];
    }

    /**
     * The bar of a production, each part linking to its artefacts when the viewer can open the list.
     *
     * @return array{total: int, counts: array<string, int>, routes: array<string, array{name: string, parameters: array<string, string>}>|null}
     */
    public function forProduction(Production $production, User $user): array
    {
        $parameters = ['organisation' => $production->organisation->slug, 'production' => $production->slug];

        return [
            ...$this->handle('production_id', $production->id),
            'routes' => IndexArtefacts::canSeeProductionArtefacts($user, $production)
                ? collect(self::STATUSES)->mapWithKeys(fn (string $status) => [$status => [
                    'name'       => 'grp.org.productions.show.crafts.artefacts.index',
                    'parameters' => [...$parameters, 'artefacts_elements[costing]' => $status],
                ]])->all()
                : null,
        ];
    }

    /**
     * The bar of the organisation that makes a master shop's products; parts link only when it has a single production.
     *
     * @return array{total: int, counts: array<string, int>, routes: array<string, array{name: string, parameters: array<string, string>}>|null}|null
     */
    public function forOrganisation(Organisation $organisation, User $user): ?array
    {
        $productions = Production::where('organisation_id', $organisation->id)->get();
        if ($productions->isEmpty()) {
            return null;
        }

        if ($productions->count() === 1) {
            return $this->forProduction($productions->first(), $user);
        }

        return [...$this->handle('organisation_id', $organisation->id), 'routes' => null];
    }
}
