<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierSubmission\UI;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierShareLink\UI\GetSupplierPortalSummary;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\Http\Resources\SupplyChain\SupplierSubmissionsResource;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\SupplierSubmission;
use App\Models\SysAdmin\Group;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * The queue of what suppliers sent from their pages, the ones waiting for us first.
 */
class IndexSupplierSubmissions extends OrgAction
{
    use WithSupplyChainAuthorisation;

    /**
     * @return array<string, array{label: string, elements: array<string, array{0: string, 1: int}>, engine: Closure}>
     */
    private function elementGroups(Group $group): array
    {
        $count = fn (string $column) => DB::table('supplier_submissions')->where('group_id', $group->id)
            ->where('state', '!=', SupplierSubmissionStateEnum::DRAFT->value)
            ->groupBy($column)->selectRaw("$column, count(*) as total")->pluck('total', $column);

        $elements = fn (array $labels, $counts) => collect($labels)->map(fn (string $label, string $key) => [$label, (int) ($counts[$key] ?? 0)])->all();

        return [
            'state' => [
                'label'    => __('State'),
                'elements' => $elements(collect(SupplierSubmissionStateEnum::labels())->except(SupplierSubmissionStateEnum::DRAFT->value)->all(), $count('state')),
                'engine'   => fn ($query, $elements) => $query->whereIn('supplier_submissions.state', $elements),
            ],
            'type' => [
                'label'    => __('Type'),
                'elements' => $elements(SupplierSubmissionTypeEnum::labels(), $count('type')),
                'engine'   => fn ($query, $elements) => $query->whereIn('supplier_submissions.type', $elements),
            ],
        ];
    }

    public function handle(Group $group, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('suppliers.code', $value)->orWhereAnyWordStartWith('suppliers.name', $value)->orWhereStartWith('supplier_products.code', $value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(SupplierSubmission::class)
            ->join('suppliers', 'suppliers.id', 'supplier_submissions.supplier_id')
            ->leftJoin('supplier_products', 'supplier_products.id', 'supplier_submissions.supplier_product_id')
            ->leftJoin('currencies', 'currencies.id', 'suppliers.currency_id')
            ->where('supplier_submissions.group_id', $group->id)
            ->where('supplier_submissions.state', '!=', SupplierSubmissionStateEnum::DRAFT->value);

        foreach ($this->elementGroups($group) as $key => $elementGroup) {
            $query->whereElementGroup(key: $key, allowedElements: array_keys($elementGroup['elements']), engine: $elementGroup['engine'], prefix: $prefix);
        }

        return $query
            ->defaultSort('waiting')
            ->select([
                'supplier_submissions.id',
                'supplier_submissions.type',
                'supplier_submissions.state',
                'supplier_submissions.submitted_at',
                'supplier_submissions.decided_at',
                'supplier_submissions.data',
                'suppliers.code as supplier_code',
                'suppliers.name as supplier_name',
                'suppliers.slug as supplier_slug',
                'supplier_products.code as supplier_product_code',
                'supplier_products.name as supplier_product_name',
                'supplier_products.slug as supplier_product_slug',
                'supplier_products.cost as supplier_product_cost',
                'currencies.code as currency_code',
            ])
            ->selectRaw("case when supplier_submissions.state in ('received', 'under_review') then 0 else 1 end as waiting")
            ->allowedSorts([
                'submitted_at',
                'supplier_code',
                'supplier_product_code',
                'type',
                'state',
                AllowedSort::callback('waiting', fn ($query, bool $descending) => $query
                    ->orderByRaw("case when supplier_submissions.state in ('received', 'under_review') then 0 else 1 end ".($descending ? 'desc' : 'asc'))
                    ->orderBy('supplier_submissions.submitted_at')),
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Group $group, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($group, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            foreach ($this->elementGroups($group) as $key => $elementGroup) {
                $table->elementGroup(key: $key, label: $elementGroup['label'], elements: $elementGroup['elements']);
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('submission'), __('submissions')])
                ->withEmptyState(['title' => __('Nothing sent by suppliers yet')])
                ->column(key: 'submitted_at', label: __('Sent'), canBeHidden: false, sortable: true)
                ->column(key: 'supplier_code', label: __('Supplier'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'supplier_product_code', label: __('Product'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'type', label: __('Type'), canBeHidden: false, sortable: true)
                ->column(key: 'summary', label: __('What'), canBeHidden: false)
                ->column(key: 'state', label: __('State'), canBeHidden: false, sortable: true)
                ->defaultSort('waiting');
        };
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);

        return $this->handle($group);
    }

    public function htmlResponse(LengthAwarePaginator $submissions): Response
    {
        return Inertia::render('SupplyChain/SupplierSubmissions', [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'title'       => __('Supplier submissions'),
            'pageHead'    => [
                'title' => __('Supplier submissions'),
                'icon'  => ['icon' => ['fal', 'fa-inbox-in'], 'title' => __('Supplier submissions')],
            ],
            'data'        => SupplierSubmissionsResource::collection($submissions),
            'portalSettings' => [
                'can'    => $this->canEdit,
                'values' => GetSupplierPortalSummary::choSettings($this->group),
                'starts' => collect(GetSupplierPortalSummary::choStartLabels())->map(fn (string $label, string $value) => ['value' => $value, 'label' => ucfirst($label)])->values()->all(),
                'route'  => ['name' => 'grp.models.supplier_portal_settings.update', 'parameters' => []],
            ],
        ])->table($this->tableStructure($this->group));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowSupplyChainDashboard::make()->getBreadcrumbs(),
            [[
                'type'   => 'simple',
                'simple' => [
                    'label' => __('Supplier submissions'),
                    'icon'  => 'fal fa-bars',
                    'route' => ['name' => 'grp.supply-chain.supplier_submissions.index'],
                ],
            ]]
        );
    }
}
