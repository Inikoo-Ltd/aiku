<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 28 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * Every purchase order sent to the supplier, for its own page: the open ones first. Drafts were never sent, so they
 * stay out.
 */
class IndexSupplierPortalPurchaseOrders
{
    use AsObject;

    /**
     * @var array<string, list<string>>
     */
    private const array STATES = [
        'open'      => [PurchaseOrderStateEnum::SUBMITTED->value, PurchaseOrderStateEnum::CONFIRMED->value],
        'received'  => [PurchaseOrderStateEnum::SETTLED->value],
        'cancelled' => [PurchaseOrderStateEnum::CANCELLED->value, PurchaseOrderStateEnum::NOT_RECEIVED->value],
    ];

    private function sent(Supplier $supplier): Builder
    {
        return PurchaseOrder::query()
            ->where('purchase_orders.parent_type', 'OrgSupplier')
            ->whereIn('purchase_orders.parent_id', DB::table('org_suppliers')->where('supplier_id', $supplier->id)->select('id'))
            ->whereIn('purchase_orders.state', array_merge(...array_values(self::STATES)));
    }

    public function openCount(Supplier $supplier): int
    {
        return $this->sent($supplier)->whereIn('purchase_orders.state', self::STATES['open'])->count();
    }

    /**
     * The Open / Received / Cancelled capsules over the table, with how many of each.
     *
     * @return array{facets: array{state: list<array{value: string, label: string, count: int}>}, active_facets: array{state: string|null}}
     */
    public function facets(Supplier $supplier, string $prefix): array
    {
        $labels = ['open' => __('Open'), 'received' => __('Received'), 'cancelled' => __('Cancelled')];

        return [
            'facets'        => ['state' => collect(self::STATES)->map(fn (array $states, string $key) => [
                'value' => $key,
                'label' => $labels[$key],
                'count' => $this->sent($supplier)->whereIn('purchase_orders.state', $states)->count(),
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
            $query->whereStartWith('purchase_orders.reference', $value);
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for($this->sent($supplier))
            ->join('organisations', 'organisations.id', 'purchase_orders.organisation_id')
            ->leftJoin('currencies', 'currencies.id', 'purchase_orders.currency_id');

        if ($state = $this->selectedState($prefix)) {
            $query->whereIn('purchase_orders.state', self::STATES[$state]);
        }

        return $query
            ->defaultSort('-submitted_at')
            ->select([
                'purchase_orders.id',
                'purchase_orders.reference',
                'purchase_orders.state',
                'purchase_orders.delivery_state',
                'purchase_orders.submitted_at',
                'purchase_orders.confirmed_at',
                'purchase_orders.estimated_received_at',
                'purchase_orders.settled_at',
                'purchase_orders.cost_items',
                'organisations.name as organisation_name',
                'currencies.code as currency_code',
            ])
            ->selectRaw('(select count(*) from purchase_order_transactions t where t.purchase_order_id = purchase_orders.id and t.deleted_at is null) as number_lines')
            ->allowedSorts(['reference', 'submitted_at', 'confirmed_at', 'estimated_received_at', 'settled_at', 'cost_items'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('order'), __('orders')])
                ->withEmptyState(['title' => __('No purchase orders')])
                ->column(key: 'reference', label: __('Order'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'organisation_name', label: __('Ordered by'), canBeHidden: false)
                ->column(key: 'state', label: __('Status'), canBeHidden: false)
                ->column(key: 'submitted_at', label: __('Sent'), canBeHidden: false, sortable: true)
                ->column(key: 'confirmed_at', label: __('Confirmed'), sortable: true)
                ->column(key: 'estimated_received_at', label: __('Expected'), sortable: true)
                ->column(key: 'settled_at', label: __('Received'), sortable: true)
                ->column(key: 'number_lines', label: __('Lines'), align: 'right')
                ->column(key: 'cost_items', label: __('Amount'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'labels', label: __('Labels'), canBeHidden: false)
                ->defaultSort('-submitted_at');
        };
    }
}
