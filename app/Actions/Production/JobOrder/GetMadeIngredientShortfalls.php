<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 16:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\JobOrder;

use App\Actions\Production\PartnerShippingList\GetProductionSurplusInPipeline;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Models\Production\Production;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Products made in the factory that another recipe uses as a raw material (JBB-04 bath bombs shrink-wrapped
 * into PuckJBB-04, HCS soaps into SOAP, HELP-4837). Stock is shared out to the open job lines first, in plan
 * order, then to the backlog lines in the order given; what stock and the ingredient's surplus in production
 * (made for no order, GetProductionSurplusInPipeline) cannot cover is short. A job line only shows it while the
 * step using the ingredient is not done. Quantities per unit are in trade units (pieces), as
 * ReceiveJobOrderIntoStock deducts them.
 * ponytail: job lines claim their whole unreceived quantity, since the ingredient leaves stock when the line is
 * received; the same surplus can also cover the ingredient's own backlog lines on the board.
 */
class GetMadeIngredientShortfalls
{
    use AsObject;

    private int $productionId;

    /**
     * @param  Collection<int, array{key: int|string, artefact_id: int|null, units: float}>  $backlog  in the order stock goes to them
     * @return array{job_items: array<int, list<array<string, mixed>>>, job_cards: array<string, list<array<string, mixed>>>, backlog: array<int|string, list<array<string, mixed>>>}
     */
    public function handle(Production $production, ?Collection $backlog = null): array
    {
        $this->productionId = $production->id;
        $backlog  = ($backlog ?? collect())->filter(fn (array $line) => $line['artefact_id'] && $line['units'] > 0);
        $jobLines = DB::table('job_order_items')
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->where('job_orders.production_id', $production->id)
            ->whereIn('job_orders.state', array_map(fn (JobOrderStateEnum $state) => $state->value, JobOrderStateEnum::open()))
            ->whereNull('job_orders.deleted_at')
            ->whereNull('job_order_items.deleted_at')
            ->whereNotNull('job_order_items.artefact_id')
            ->orderByRaw('job_orders.planned_for nulls last')
            ->orderByRaw('job_orders.plan_position nulls last')
            ->orderBy('job_orders.id')
            ->orderBy('job_order_items.id')
            ->get([
                'job_order_items.id',
                'job_order_items.artefact_id',
                'job_order_items.job_order_id',
                DB::raw('greatest(0, job_order_items.quantity - coalesce(job_order_items.quantity_received, 0)) as units'),
            ]);

        $ingredients = $this->ingredientsOf($production, $jobLines->pluck('artefact_id')->merge($backlog->pluck('artefact_id'))->unique()->values());
        if ($ingredients->isEmpty()) {
            return ['job_items' => [], 'job_cards' => [], 'backlog' => []];
        }

        $pools        = $this->pools($ingredients);
        $pendingSteps = DB::table('job_order_item_tasks')
            ->whereIn('job_order_item_id', $jobLines->pluck('id'))
            ->where('state', '!=', JobOrderItemTaskStateEnum::DONE->value)
            ->whereNotNull('artefact_manufacture_task_id')
            ->get(['job_order_item_id', 'artefact_manufacture_task_id'])
            ->groupBy('job_order_item_id')
            ->map(fn (Collection $tasks) => $tasks->pluck('artefact_manufacture_task_id')->map(fn ($id) => (int) $id)->all());

        $result = ['job_items' => [], 'backlog' => []];
        foreach ($jobLines as $line) {
            $result['job_items'][$line->id] = collect($this->claim($pools, $ingredients->get($line->artefact_id, collect()), (float) $line->units))
                ->filter(fn (array $shortfall) => array_intersect($shortfall['step_ids'], $pendingSteps->get($line->id, [])))
                ->map(fn (array $shortfall) => Arr::except($shortfall, 'step_ids'))
                ->values()
                ->all();
        }
        foreach ($backlog as $line) {
            $result['backlog'][$line['key']] = array_map(fn (array $shortfall) => Arr::except($shortfall, 'step_ids'), $this->claim($pools, $ingredients->get($line['artefact_id'], collect()), (float) $line['units']));
        }

        $result['job_items'] = array_filter($result['job_items']);
        $result['backlog']   = array_filter($result['backlog']);
        $result['job_cards'] = $jobLines->filter(fn (object $line) => isset($result['job_items'][$line->id]))
            ->groupBy(fn (object $line) => self::jobCardKey($line->job_order_id, $line->artefact_id))
            ->map(fn (Collection $lines) => self::merge($lines->flatMap(fn (object $line) => $result['job_items'][$line->id])))
            ->all();

        return $result;
    }

    public static function jobCardKey(int|string $jobOrderId, int|string $artefactId): string
    {
        return $jobOrderId.':'.$artefactId;
    }

    /**
     * One entry per ingredient for a board card that stands for several lines of a job order.
     *
     * @param  Collection<int, array<string, mixed>>  $shortfalls
     * @return list<array<string, mixed>>
     */
    private static function merge(Collection $shortfalls): array
    {
        return $shortfalls->groupBy('code')->map(function (Collection $entries) {
            $first        = $entries->first();
            $neededPieces = round($entries->sum('needed_pieces'), 3);
            $shortPieces  = round($entries->sum('short_pieces'), 3);
            $notInStock   = round($entries->sum('not_in_stock_pieces'), 3);

            return array_merge($first, [
                'needed_pieces'     => $neededPieces,
                'not_in_stock_pieces' => $notInStock,
                'not_in_stock_skos' => (int) ceil($notInStock / $first['packed_in'] - 1e-9),
                'short_pieces'      => $shortPieces,
                'short_skos'        => (int) ceil($shortPieces / $first['packed_in'] - 1e-9),
                'waiting_for'       => $entries->pluck('waiting_for')->flatten()->unique()->values()->all(),
            ]);
        })->values()->all();
    }

    /**
     * Per artefact, the raw materials of its recipe made here, with the pieces one artefact takes and the recipe steps
     * using them. Made means the SKO's artefact is in process, active or dormant and is made from raw materials other than its own SKO: bulk
     * oils list their own SKO (HELP-4770), and bought SKOs given an artefact (PF-01, DB-01) list none.
     *
     * @param  Collection<int, int>  $artefactIds
     * @return Collection<int, Collection<int, object>>
     */
    private function ingredientsOf(Production $production, Collection $artefactIds): Collection
    {
        if ($artefactIds->isEmpty()) {
            return collect();
        }

        return DB::table('artefacts_manufacture_tasks as recipe_steps')
            ->join('artefacts', 'artefacts.id', 'recipe_steps.artefact_id')
            ->join('recipe_step_raw_materials', 'recipe_step_raw_materials.artefact_manufacture_task_id', 'recipe_steps.id')
            ->join('raw_materials', 'raw_materials.id', 'recipe_step_raw_materials.raw_material_id')
            ->join('org_stocks', 'org_stocks.id', 'raw_materials.org_stock_id')
            ->joinLateral(
                DB::table('artefacts as ingredients')
                    ->whereColumn('ingredients.org_stock_id', 'raw_materials.org_stock_id')
                    ->whereNull('ingredients.deleted_at')
                    ->whereIn('ingredients.state', [ArtefactStateEnum::IN_PROCESS->value, ArtefactStateEnum::ACTIVE->value, ArtefactStateEnum::DORMANT->value])
                    ->whereExists(fn ($query) => $query->selectRaw('1')
                        ->from('artefacts_manufacture_tasks as ingredient_steps')
                        ->join('recipe_step_raw_materials as ingredient_materials', 'ingredient_materials.artefact_manufacture_task_id', 'ingredient_steps.id')
                        ->whereColumn('ingredient_steps.artefact_id', 'ingredients.id'))
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')
                        ->from('artefacts_manufacture_tasks as ingredient_steps')
                        ->join('recipe_step_raw_materials as ingredient_materials', 'ingredient_materials.artefact_manufacture_task_id', 'ingredient_steps.id')
                        ->join('raw_materials as own_materials', 'own_materials.id', 'ingredient_materials.raw_material_id')
                        ->whereColumn('ingredient_steps.artefact_id', 'ingredients.id')
                        ->whereColumn('own_materials.org_stock_id', 'ingredients.org_stock_id'))
                    ->orderByRaw('ingredients.production_id = ? desc', [$production->id])
                    ->orderBy('ingredients.id')
                    ->limit(1)
                    ->select(['ingredients.id', 'ingredients.production_id']),
                'ingredient'
            )
            ->whereIn('recipe_steps.artefact_id', $artefactIds)
            ->whereNull('raw_materials.deleted_at')
            ->whereRaw('raw_materials.org_stock_id != coalesce(artefacts.org_stock_id, 0)')
            ->where('recipe_step_raw_materials.quantity_per_unit', '>', 0)
            ->groupBy('recipe_steps.artefact_id', 'org_stocks.id', 'ingredient.id', 'ingredient.production_id')
            ->get([
                'recipe_steps.artefact_id',
                'org_stocks.id as org_stock_id',
                'org_stocks.code',
                DB::raw('greatest(1, coalesce(org_stocks.packed_in, 1)) as packed_in'),
                DB::raw('greatest(0, coalesce(org_stocks.quantity_available, 0)) as available_skos'),
                'ingredient.id as ingredient_artefact_id',
                'ingredient.production_id as ingredient_production_id',
                DB::raw('sum(recipe_step_raw_materials.quantity_per_unit) as pieces_per_unit'),
                DB::raw("string_agg(recipe_steps.id::text, ',') as step_ids"),
            ])
            ->groupBy('artefact_id');
    }

    /**
     * What each ingredient has to share out: pieces in stock, then pieces its jobs make for no order. A JBB job
     * raised for a customer or partner line is spoken for, so it never covers PuckJBB.
     *
     * @param  Collection<int, Collection<int, object>>  $ingredients
     * @return array<int, array{stock: float, coming: float, coming_from: array<int, string>}>
     */
    private function pools(Collection $ingredients): array
    {
        $ingredients = $ingredients->flatten(1)->unique('org_stock_id');
        $surplus     = GetProductionSurplusInPipeline::run($ingredients->pluck('org_stock_id')->all());

        return $ingredients->mapWithKeys(fn (object $ingredient) => [$ingredient->org_stock_id => [
            'stock'       => (float) $ingredient->available_skos * (float) $ingredient->packed_in,
            'coming'      => ((float) Arr::get($surplus, $ingredient->org_stock_id.'.pending_booking', 0) + (float) Arr::get($surplus, $ingredient->org_stock_id.'.in_production', 0)) * (float) $ingredient->packed_in,
            'coming_from' => Arr::get($surplus, $ingredient->org_stock_id.'.job_orders', []),
        ]])->all();
    }

    /**
     * Takes what a line needs from the pools, stock first, and returns the ingredients it cannot have from stock.
     *
     * @param  array<int, array{stock: float, coming: float, coming_from: array<int, string>}>  $pools
     * @param  Collection<int, object>  $ingredients
     * @return list<array<string, mixed>>
     */
    private function claim(array &$pools, Collection $ingredients, float $units): array
    {
        $shortfalls = [];
        foreach ($ingredients as $ingredient) {
            $id        = $ingredient->org_stock_id;
            $needed    = round($units * (float) $ingredient->pieces_per_unit, 3);
            $fromStock = min($needed, $pools[$id]['stock']);
            $pools[$id]['stock'] -= $fromStock;
            $notInStock = round($needed - $fromStock, 3);
            if ($notInStock <= 0) {
                continue;
            }

            $fromJobs = min($notInStock, $pools[$id]['coming']);
            $pools[$id]['coming'] -= $fromJobs;
            $short    = round($notInStock - $fromJobs, 3);
            $packedIn = (float) $ingredient->packed_in;

            $shortfalls[] = [
                'code'              => $ingredient->code,
                'artefact_id'       => (int) $ingredient->ingredient_artefact_id,
                'is_made_here'      => $ingredient->ingredient_production_id == $this->productionId,
                'packed_in'         => $packedIn,
                'needed_pieces'     => $needed,
                'not_in_stock_pieces' => $notInStock,
                'not_in_stock_skos' => (int) ceil($notInStock / $packedIn - 1e-9),
                'short_pieces'      => $short,
                'short_skos'        => (int) ceil($short / $packedIn - 1e-9),
                'waiting_for'       => $fromJobs > 0 ? array_values(array_unique($pools[$id]['coming_from'])) : [],
                'step_ids'          => array_map('intval', explode(',', $ingredient->step_ids)),
            ];
        }

        return $shortfalls;
    }
}
