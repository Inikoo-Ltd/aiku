<?php

/*
 * author Louis Perez
 * created on 16-12-2025-15h-36m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Masters\MasterVariant;

use App\Actions\OrgAction;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterVariant;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\Sorts\Sort;

class IndexMasterVariant extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(MasterProductCategory $parent,  $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_variants.code', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(MasterVariant::class);

        if($parent instanceof MasterProductCategory){
            if ($parent->type == MasterProductCategoryTypeEnum::FAMILY) {
                $queryBuilder->where('master_variants.master_family_id', $parent->id);
            } else if ($parent->type == MasterProductCategoryTypeEnum::SUB_DEPARTMENT) {
                $queryBuilder->where('master_variants.master_sub_department_id', $parent->id);
            } else if ($parent->type == MasterProductCategoryTypeEnum::DEPARTMENT) {
                $queryBuilder->where('master_variants.master_department_id', $parent->id);
            } else {
                abort(419);
            }
        }
        
        return $queryBuilder
            ->defaultSort('master_variants.code')
            ->select([
                'master_variants.id',
                'master_variants.slug',
                'master_variants.code',
                'master_variants.leader_id',
                'master_variants.number_minions',
                'master_variants.number_dimensions',
                'master_variants.number_used_slots',
                'master_variants.number_used_slots_for_sale',
                'master_variants.data',
            ])
            ->allowedSorts([
                'code',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(MasterProductCategory $parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->defaultSort('code')
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'MasterProductCategory' => [
                            'title' => __("No families found"),
                            'count' => 0,
                        ],
                        default => null
                    }
                )
                ->withLabelRecord([__('variant'),__('variants')])
                ->withGlobalSearch();

            $table->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'leader_id', label: __('Leader ID'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_minions', label: __('Invoices'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_dimensions', label: __('Options'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_used_slots', label: __('Amount Used'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'number_used_slots_for_sale', label: __('Amount Used For Sale'), canBeHidden: false, sortable: false, searchable: false, align: 'right')
                ->column(key: 'data', label: __('Details'), canBeHidden: false, sortable: false, searchable: false, align: 'right');
        };
    }

    /**
     * @throws \Throwable
     */
    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): MasterVariant
    {
        $this->initialisationFromGroup($masterProductCategory->group, $request);

        return $this->handle($masterProductCategory, $this->validatedData);
    }
}
