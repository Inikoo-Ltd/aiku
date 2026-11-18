<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ProcurementNote\UI;

use App\Enums\Procurement\ProcurementNote\ProcurementNoteVisibilityEnum;
use App\InertiaTable\InertiaTable;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Procurement\ProcurementNote;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\QueryBuilder\AllowedFilter;

class IndexProcurementNotes
{
    use AsObject;

    /**
     * A supplier lists the notes of its products too, and a stock delivery those of its purchase
     * orders. With an organisation, notes written for another organisation only are left out;
     * $forAgent keeps only what was marked as visible to the agent.
     */
    public function handle(Supplier|SupplierProduct|Agent|PurchaseOrder|StockDelivery $subject, ?string $prefix = null, ?Organisation $organisation = null, bool $forAgent = false): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(fn ($query) => $query
                ->where('procurement_notes.body', 'ILIKE', "%$value%")
                ->orWhere('procurement_notes.title', 'ILIKE', "%$value%"));
        });

        $queryBuilder = QueryBuilder::for(ProcurementNote::class)
            ->leftJoin('users', 'users.id', 'procurement_notes.author_user_id')
            ->leftJoin('purchase_orders', function ($join) {
                $join->on('purchase_orders.id', DB::raw("coalesce(procurement_notes.purchase_order_id, case when procurement_notes.subject_type = 'PurchaseOrder' then procurement_notes.subject_id end)"));
            })
            ->leftJoin('supplier_products', function ($join) {
                $join->on('supplier_products.id', 'procurement_notes.subject_id')->where('procurement_notes.subject_type', 'SupplierProduct');
            })
            ->leftJoin('currencies', 'currencies.id', 'procurement_notes.incident_currency_id')
            ->leftJoin('organisations', 'organisations.id', 'procurement_notes.organisation_id')
            ->joinSub($this->distinctNotes($subject, $organisation, $forAgent), 'distinct_notes', 'distinct_notes.id', 'procurement_notes.id')
            ->select([
                'distinct_notes.number_documents',
                'procurement_notes.*',
                'users.contact_name as user_name',
                'purchase_orders.reference as purchase_order_reference',
                'supplier_products.code as supplier_product_code',
                'currencies.code as incident_currency_code',
                'organisations.code as organisation_code',
            ])
            ->selectRaw('? as listed_on', [$subject->getMorphClass()]);

        return $queryBuilder
            ->orderByDesc('procurement_notes.is_pinned')
            ->orderByDesc('procurement_notes.created_at')
            ->orderByDesc('procurement_notes.id')
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    /**
     * A note copied onto several purchase orders of a stock delivery is one note: same text, author and minute.
     */
    private function distinctNotes(Supplier|SupplierProduct|Agent|PurchaseOrder|StockDelivery $subject, ?Organisation $organisation, bool $forAgent): Builder
    {
        return DB::table('procurement_notes')
            ->where(function ($query) use ($subject) {
                $query->where(fn ($query) => $query->where('procurement_notes.subject_type', $subject->getMorphClass())->where('procurement_notes.subject_id', $subject->id));

                if ($subject instanceof StockDelivery) {
                    $query->orWhere(fn ($query) => $query->where('procurement_notes.subject_type', 'PurchaseOrder')
                        ->whereIn('procurement_notes.subject_id', $subject->purchaseOrders()->pluck('purchase_orders.id')));
                }

                if ($subject instanceof Supplier) {
                    $query->orWhere(fn ($query) => $query->where('procurement_notes.subject_type', 'SupplierProduct')
                        ->whereIn('procurement_notes.subject_id', $subject->supplierProducts()->select('supplier_products.id')));
                }
            })
            ->when($organisation, fn ($query) => $query->where(fn ($query) => $query
                ->whereNull('procurement_notes.organisation_id')
                ->orWhere('procurement_notes.organisation_id', $organisation->id)))
            ->when($forAgent, fn ($query) => $query->where('procurement_notes.visibility', ProcurementNoteVisibilityEnum::AGENT->value))
            ->groupByRaw("procurement_notes.subject_type, case when procurement_notes.subject_type = 'PurchaseOrder' then 0 else procurement_notes.id end, procurement_notes.title, procurement_notes.body, procurement_notes.author_user_id, procurement_notes.author_name, date_trunc('minute', procurement_notes.created_at)")
            ->selectRaw('min(procurement_notes.id) as id, count(*) as number_documents');
    }

    public function tableStructure(?string $prefix = null, bool $withOrganisation = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $withOrganisation) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withEmptyState(['title' => __('No notes yet')])
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false)
                ->column(key: 'category', label: __('Category'), canBeHidden: false)
                ->column(key: 'note', label: __('Note'), canBeHidden: false)
                ->column(key: 'author', label: __('Author'), canBeHidden: false);

            if ($withOrganisation) {
                $table->column(key: 'organisation_code', label: __('Organisation'), canBeHidden: false);
            }

            $table->column(key: 'actions', label: '', canBeHidden: false);
        };
    }
}
