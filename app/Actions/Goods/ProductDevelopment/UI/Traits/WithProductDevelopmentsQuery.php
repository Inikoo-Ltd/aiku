<?php

/*
 * Author Louis Perez
 * Created on 15-10-2026-17h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI\Traits;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStageStateEnum;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentStatusEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

trait WithProductDevelopmentsQuery
{
    public function baseProductDevelopmentsQuery(Group $group, User $user): Builder
    {
        $query = ProductDevelopment::query()
            ->where('product_developments.group_id', $group->id)
            ->leftJoin('product_development_stats', 'product_development_stats.product_development_id', '=', 'product_developments.id')
            ->leftJoin('users as owners', 'owners.id', '=', 'product_developments.owner_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'product_developments.supplier_id')
            ->leftJoin('agents', 'agents.id', '=', 'product_developments.agent_id')
            ->leftJoin('trade_unit_families', 'trade_unit_families.id', '=', 'product_developments.trade_unit_family_id');

        return $this->productDevelopmentAccess()->restrictToVisible($query, $user);
    }

    public function productDevelopmentsQuery(Group $group, User $user): QueryBuilder
    {
        return QueryBuilder::for($this->baseProductDevelopmentsQuery($group, $user))
            ->select([
                'product_developments.id',
                'product_developments.slug',
                'product_developments.code',
                'product_developments.name',
                'product_developments.type',
                'product_developments.kind',
                'product_developments.status',
                'product_developments.current_position',
                'product_developments.target_online_date',
                'product_developments.owner_id',
                'product_development_stats.number_required_missing',
                'product_development_stats.number_items',
                'product_development_stats.number_required_missing_buyer',
                'product_development_stats.number_required_missing_agent',
                'product_development_stats.number_required_missing_purchasing',
                'product_development_stats.number_required_missing_compliance',
                'product_development_stats.number_required_missing_designer',
                'product_development_stats.number_required_missing_webmaster',
                'product_development_stats.number_required_missing_approver',
                'owners.contact_name as owner_name',
                'suppliers.name as supplier_name',
                'agents.name as agent_name',
                'trade_unit_families.code as trade_unit_family_code',
                'trade_unit_families.name as trade_unit_family_name',
            ])
            ->selectSub($this->brandNameSubQuery(), 'brand_name')
            ->selectSub($this->currentStageStartedAtSubQuery(), 'current_stage_started_at')
            ->selectSub($this->isOverdueSubQuery(), 'is_overdue')
            ->selectSub($this->requiredChecklistCountSubQuery(), 'number_required')
            ->selectSub($this->currentStageDueAtSubQuery(), 'current_stage_due_at')
            ->with('owner')
            ->allowedFilters($this->productDevelopmentFilters());
    }

    /**
     * @return array{all: int, status: array<string, int>, missing: int, overdue: int}
     */
    public function productDevelopmentCounts(Group $group, User $user): array
    {
        $base = $this->baseProductDevelopmentsQuery($group, $user);

        $statusCounts = $base->clone()
            ->selectRaw('product_developments.status, count(*) as number')
            ->groupBy('product_developments.status')
            ->pluck('number', 'status')
            ->all();

        return [
            'all'     => $base->clone()->count(),
            'status'  => collect(ProductDevelopmentStatusEnum::values())->mapWithKeys(fn (string $status) => [$status => (int) ($statusCounts[$status] ?? 0)])->all(),
            'missing' => $base->clone()->where('product_development_stats.number_required_missing', '>', 0)->count(),
            'overdue' => $base->clone()->whereRaw('('.$this->isOverdueSql().')')->count(),
        ];
    }

    /**
     * @return array<int, AllowedFilter>
     */
    public function productDevelopmentFilters(): array
    {
        return [
            AllowedFilter::callback('global', function (Builder $query, $value) {
                $query->where(function (Builder $query) use ($value) {
                    $query->whereStartWith('product_developments.code', $value)
                        ->orWhereAnyWordStartWith('product_developments.name', $value)
                        ->orWhereStartWith('trade_unit_families.code', $value);
                });
            }),
            AllowedFilter::callback('status', fn (Builder $query, $value) => $query->where('product_developments.status', $value)),
            AllowedFilter::callback('kind', fn (Builder $query, $value) => $query->where('product_developments.kind', (string) $value)),
            AllowedFilter::callback('stage', fn (Builder $query, $value) => $query->where('product_developments.current_position', (int) $value)),
            AllowedFilter::callback('agent', fn (Builder $query, $value) => $query->where('product_developments.agent_id', (int) $value)),
            AllowedFilter::callback('brand', fn (Builder $query, $value) => $query->whereExists(fn ($subQuery) => $subQuery->select(DB::raw(1))
                ->from('model_has_brands')
                ->where('model_has_brands.model_type', 'ProductDevelopment')
                ->whereColumn('model_has_brands.model_id', 'product_developments.id')
                ->where('model_has_brands.brand_id', (int) $value))),
            AllowedFilter::callback('designer', fn (Builder $query, $value) => $query->whereExists(fn ($subQuery) => $subQuery->select(DB::raw(1))
                ->from('product_development_members')
                ->whereColumn('product_development_members.product_development_id', 'product_developments.id')
                ->where('product_development_members.role', ProductDevelopmentMemberRoleEnum::DESIGNER->value)
                ->where('product_development_members.user_id', (int) $value))),
            AllowedFilter::callback('missing', fn (Builder $query) => $query->where('product_development_stats.number_required_missing', '>', 0)),
            AllowedFilter::callback('overdue', fn (Builder $query) => $query->whereRaw('('.$this->isOverdueSql().')')),
        ];
    }

    private function brandNameSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('model_has_brands')
            ->join('brands', 'brands.id', '=', 'model_has_brands.brand_id')
            ->where('model_has_brands.model_type', 'ProductDevelopment')
            ->whereColumn('model_has_brands.model_id', 'product_developments.id')
            ->select('brands.name')
            ->limit(1);
    }

    private function currentStageStartedAtSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('product_development_stages')
            ->whereColumn('product_development_stages.product_development_id', 'product_developments.id')
            ->whereColumn('product_development_stages.position', 'product_developments.current_position')
            ->selectRaw('min(product_development_stages.started_at)');
    }

    private function requiredChecklistCountSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('product_development_checklist_items')
            ->whereColumn('product_development_checklist_items.product_development_id', 'product_developments.id')
            ->where('product_development_checklist_items.required', true)
            ->selectRaw('count(*)');
    }

    private function currentStageDueAtSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('product_development_stages')
            ->whereColumn('product_development_stages.product_development_id', 'product_developments.id')
            ->whereColumn('product_development_stages.position', 'product_developments.current_position')
            ->selectRaw('min(product_development_stages.due_at)');
    }

    private function isOverdueSubQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::query()->selectRaw($this->isOverdueSql());
    }

    private function isOverdueSql(): string
    {
        $active     = ProductDevelopmentStatusEnum::ACTIVE->value;
        $inProgress = ProductDevelopmentStageStateEnum::IN_PROGRESS->value;

        return "product_developments.status = '$active' and (product_developments.target_online_date < current_date or exists (select 1 from product_development_stages pds where pds.product_development_id = product_developments.id and pds.position = product_developments.current_position and pds.state = '$inProgress' and pds.due_at < current_date))";
    }
}
