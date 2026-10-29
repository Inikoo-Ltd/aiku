<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionStateEnum;
use App\Enums\SupplyChain\SupplierSubmission\SupplierSubmissionTypeEnum;
use App\InertiaTable\InertiaTable;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierSubmission;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every product of the supplier at the price we pay today, each with the last price change the supplier proposed for
 * it, so the supplier picks the product to change from the list. The capsules narrow it to the products with a change
 * waiting for us, accepted or declined.
 */
class IndexSupplierPortalPriceChanges
{
    use AsObject;

    /**
     * @var array<string, list<string>>
     */
    private const array STATES = [
        'proposed'       => [SupplierSubmissionStateEnum::RECEIVED->value, SupplierSubmissionStateEnum::UNDER_REVIEW->value, SupplierSubmissionStateEnum::MORE_INFORMATION_REQUESTED->value],
        'accepted'       => [SupplierSubmissionStateEnum::ACCEPTED->value],
        'not_proceeding' => [SupplierSubmissionStateEnum::NOT_PROCEEDING->value],
    ];

    private function products(Supplier $supplier): Builder
    {
        $lastPriceChange = SupplierSubmission::query()
            ->whereColumn('supplier_submissions.supplier_product_id', 'supplier_products.id')
            ->where('supplier_submissions.type', SupplierSubmissionTypeEnum::PRICE_CHANGE)
            ->whereNotNull('supplier_submissions.submitted_at')
            ->latest('supplier_submissions.id')
            ->limit(1)
            ->select(['supplier_submissions.id', 'supplier_submissions.state', 'supplier_submissions.data', 'supplier_submissions.feedback', 'supplier_submissions.submitted_at']);

        return SupplierProduct::query()
            ->where('supplier_products.supplier_id', $supplier->id)
            ->where('supplier_products.state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->leftJoinLateral($lastPriceChange, 'price_change');
    }

    /**
     * @return array{facets: array{state: list<array{value: string, label: string, count: int}>}, active_facets: array{state: string|null}}
     */
    public function facets(Supplier $supplier, string $prefix): array
    {
        $labels = ['proposed' => __('Proposed'), 'accepted' => __('Accepted'), 'not_proceeding' => __('Not proceeding')];

        return [
            'facets'        => ['state' => collect(self::STATES)->map(fn (array $states, string $key) => [
                'value' => $key,
                'label' => $labels[$key],
                'count' => $this->products($supplier)->whereIn('price_change.state', $states)->count(),
            ])->values()->all()],
            'active_facets' => ['state' => $this->selectedState($prefix)],
        ];
    }

    private function selectedState(?string $prefix): ?string
    {
        $state = request()->input(($prefix ? $prefix.'_' : '').'facets.state');

        return array_key_exists((string) $state, self::STATES) ? $state : null;
    }

    public function handle(Supplier $supplier, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query->whereStartWith('supplier_products.code', $value)->orWhereAnyWordStartWith('supplier_products.name', $value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for($this->products($supplier));
        if ($state = $this->selectedState($prefix)) {
            $query->whereIn('price_change.state', self::STATES[$state]);
        }

        return $query
            ->defaultSort('code')
            ->select([
                'supplier_products.id',
                'supplier_products.code',
                'supplier_products.name',
                'supplier_products.state',
                'supplier_products.is_available',
                'supplier_products.cost',
                'price_change.id as price_change_id',
                'price_change.state as price_change_state',
                'price_change.data as price_change_data',
                'price_change.feedback as price_change_feedback',
                'price_change.submitted_at as price_change_submitted_at',
            ])
            ->allowedSorts(['code', 'name', 'cost', 'price_change_submitted_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(Supplier $supplier, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($supplier, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('product'), __('products')])
                ->withEmptyState(['title' => __('No products'), 'count' => $this->products($supplier)->count()])
                ->column(key: 'code', label: __('Product'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'status', label: __('Status'), canBeHidden: false)
                ->column(key: 'cost', label: __('Current price'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'proposed_price', label: __('Proposed price'), canBeHidden: false, align: 'right')
                ->column(key: 'effective_from', label: __('From'))
                ->column(key: 'price_change_submitted_at', label: __('Sent'), sortable: true)
                ->column(key: 'price_change_state', label: __('Proposal'), canBeHidden: false)
                ->column(key: 'feedback', label: __('Our reply'))
                ->column(key: 'actions', label: '', canBeHidden: false)
                ->defaultSort('code');
        };
    }
}
