<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest;

use App\Actions\Production\JobOrder\UI\IndexJobOrderPlan;
use App\Enums\Production\JobOrderItemTask\JobOrderItemTaskStateEnum;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Models\Production\Production;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetDayMaterialsChecklist
{
    use AsObject;

    /**
     * Artefacts a step still has to process: the whole line until the step starts, then
     * whatever the step has not made yet.
     */
    private const string REMAINING_ARTEFACTS_SQL = 'job_order_items.quantity * greatest(0, 1 - coalesce(job_order_item_tasks.quantity_made / nullif(job_order_item_tasks.quantity_required, 0), 0))';

    /**
     * One line per material the day's steps still need, in the material's units. Only steps
     * not done count, so a packing day asks for boxes and labels and not for bottles. Mixes
     * are made in the factory and are not asked from the warehouse.
     */
    public function query(Production $production, string $date): Builder
    {
        $needs = DB::table('job_order_item_tasks')
            ->join('job_order_items', 'job_order_items.id', 'job_order_item_tasks.job_order_item_id')
            ->join('job_orders', 'job_orders.id', 'job_order_item_tasks.job_order_id')
            ->join('artefacts_manufacture_tasks as recipe_steps', 'recipe_steps.id', 'job_order_item_tasks.artefact_manufacture_task_id')
            ->join('recipe_step_raw_materials', 'recipe_step_raw_materials.artefact_manufacture_task_id', 'recipe_steps.id')
            ->join('raw_materials', 'raw_materials.id', 'recipe_step_raw_materials.raw_material_id')
            ->where('job_orders.production_id', $production->id)
            ->whereNull('job_orders.deleted_at')
            ->whereNull('job_order_items.deleted_at')
            ->whereNull('raw_materials.artefact_id')
            ->where('job_order_item_tasks.state', '!=', JobOrderItemTaskStateEnum::DONE->value)
            ->where(fn (Builder $query) => IndexJobOrderPlan::onThePlan($query, $date))
            ->groupBy('raw_materials.id')
            ->select([
                'raw_materials.id as raw_material_id',
                DB::raw('round(sum('.self::REMAINING_ARTEFACTS_SQL.' * recipe_step_raw_materials.quantity_per_unit), 3) as needed_units'),
                DB::raw("string_agg(distinct job_orders.reference, ', ' order by job_orders.reference) as job_orders"),
            ]);

        $factoryLocationId = (int) $production->factory_location_id;

        return DB::query()
            ->fromSub($needs, 'needs')
            ->join('raw_materials', 'raw_materials.id', 'needs.raw_material_id')
            ->leftJoin('org_stocks', 'org_stocks.id', 'raw_materials.org_stock_id')
            ->where('needs.needed_units', '>', 0)
            ->select([
                'needs.raw_material_id as id',
                'needs.needed_units',
                'needs.job_orders',
                'raw_materials.code',
                'raw_materials.description',
                'raw_materials.unit',
                'raw_materials.org_stock_id',
                'org_stocks.code as org_stock_code',
                DB::raw('coalesce(nullif(org_stocks.packed_in, 0), 1) as packed_in'),
                DB::raw("coalesce((select sum(factory.quantity) from location_org_stocks factory where factory.org_stock_id = raw_materials.org_stock_id and factory.location_id = $factoryLocationId), 0) * coalesce(nullif(org_stocks.packed_in, 0), 1) as at_factory_units"),
                DB::raw("coalesce((select sum(shelf.quantity) from location_org_stocks shelf join locations on locations.id = shelf.location_id where shelf.org_stock_id = raw_materials.org_stock_id and shelf.location_id != $factoryLocationId and not locations.is_goods_out and shelf.quantity > 0), 0) as warehouse_skos"),
                DB::raw('coalesce(('.self::supplierBoxUnitsSql('raw_materials.org_stock_id').'), nullif(org_stocks.packed_in, 0), 1) as box_units'),
                DB::raw('coalesce(('.$this->requestedUnitsSql($production, $date).'), 0) as requested_units'),
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function handle(Production $production, string $date): Collection
    {
        return $this->query($production, $date)->orderBy('raw_materials.code')->get();
    }

    /**
     * Units in the carton the main supplier delivers, which is how the warehouse keeps them.
     */
    public static function supplierBoxUnitsSql(string $orgStockIdExpression): string
    {
        return "select supplier_products.units_per_carton
            from org_stock_has_org_supplier_products links
            join org_supplier_products on org_supplier_products.id = links.org_supplier_product_id
            join supplier_products on supplier_products.id = org_supplier_products.supplier_product_id
            where links.org_stock_id = $orgStockIdExpression and supplier_products.units_per_carton > 0
            order by links.local_priority desc nulls last, links.id
            limit 1";
    }

    private function requestedUnitsSql(Production $production, string $date): string
    {
        return DB::table('production_material_request_items as requested')
            ->join('production_material_requests', 'production_material_requests.id', 'requested.production_material_request_id')
            ->where('production_material_requests.production_id', $production->id)
            ->whereDate('production_material_requests.plan_date', $date)
            ->whereIn('production_material_requests.state', [ProductionMaterialRequestStateEnum::SUBMITTED->value, ProductionMaterialRequestStateEnum::DELIVERED->value])
            ->whereColumn('requested.raw_material_id', 'raw_materials.id')
            ->selectRaw('sum(requested.missing_units)')
            ->toRawSql();
    }

    /**
     * Jobs of the day that need nothing because their recipe lists no materials: shown so
     * the gap in the recipe is seen, not mistaken for nothing to bring.
     *
     * @return array<int, string>
     */
    public function jobsWithoutMaterials(Production $production, string $date): array
    {
        return DB::table('job_order_items')
            ->join('job_orders', 'job_orders.id', 'job_order_items.job_order_id')
            ->join('artefacts', 'artefacts.id', 'job_order_items.artefact_id')
            ->where('job_orders.production_id', $production->id)
            ->whereNull('job_orders.deleted_at')
            ->whereNull('job_order_items.deleted_at')
            ->where(fn (Builder $query) => IndexJobOrderPlan::onThePlan($query, $date))
            ->whereNotExists(fn (Builder $query) => $query->selectRaw('1')
                ->from('artefacts_manufacture_tasks as recipe_steps')
                ->join('recipe_step_raw_materials', 'recipe_step_raw_materials.artefact_manufacture_task_id', 'recipe_steps.id')
                ->whereColumn('recipe_steps.artefact_id', 'job_order_items.artefact_id'))
            ->orderBy('job_orders.reference')
            ->get(['job_orders.reference', 'artefacts.code'])
            ->map(fn ($row) => $row->reference.' '.$row->code)
            ->unique()
            ->values()
            ->all();
    }
}
