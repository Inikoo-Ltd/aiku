<?php

/*
 * author Arya Permana - Kirin
 * created on 13-01-2025-16h-18m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\StoredItemsAudit\UI;

use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Http\Resources\Fulfilment\StoredItemAuditsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItemAudit;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexRetinaStoredItemsAudits extends RetinaAction
{
    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);

        return $this->handle($this->customer->fulfilmentCustomer);
    }

    public function handle(FulfilmentCustomer $parent, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('stored_item_audits.reference', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for(StoredItemAudit::class);
        $query->where('fulfilment_customer_id', $parent->id);

        $query->defaultSort('stored_item_audits.date');

        return $query->allowedSorts(['state', 'reference', 'date', 'amount', 'tax_amount', 'total_amount', 'number_pallets', 'number_stored_items', 'number_added_stored_items', 'number_edited_stored_items', 'number_removed_stored_items'])
            ->allowedFilters([$globalSearch,  'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

    }

    public function htmlResponse(LengthAwarePaginator $storedItemAudits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Storage/RetinaStoredItemsAudits',
            [
                'title' => __('stored item audits'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-narwhal'],
                        'title' => __('Stored item audit'),
                    ],
                    'model' => __('stored item'),
                    'title' => __('stored item audits'),

                ],
                'data' => StoredItemAuditsResource::collection($storedItemAudits),

            ]
        )->table($this->tableStructure($this->parent));
    }

    public function tableStructure(
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($modelOperations, $prefix) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                );

            $table
                ->column(key: 'state', label: ['fal', 'fa-yin-yang'], type: 'icon')
                ->column(key: 'reference', label: __('Reference'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_audited_pallets', label: __('Audited'), icon: 'fal fa-pallet', tooltip: __('Audited Pallets'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_audited_stored_items', label: __('Audited'), icon: 'fal fa-narwhal', tooltip: __('Audited Stored Items'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_audited_stored_items_with_additions', label: __('Audited (additions)'), icon: 'fal fa-narwhal', tooltip: __('Audited Stored Items (with additions)'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_audited_stored_items_with_with_subtractions', label: __('Audited (subtractions)'), icon: 'fal fa-narwhal', tooltip: __('Audited Stored Items (with subtractions)'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_audited_stored_items_with_with_stock_checked', label: __('Audited (stock checked)'), icon: 'fal fa-narwhal', tooltip: __('Audited Stored Items (with stock checked)'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_associated_stored_items', label: __('Associated'), icon: 'fal fa-narwhal', tooltip: __('Associated Stored Items'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_created_stored_items', label: __('Created'), icon: 'fal fa-narwhal', tooltip: __('Created Stored Items'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Created At'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type' => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'retina.fulfilment.storage.stored-items-audits.index',
                        ],
                        'label' => __('SKUs Audits'),
                        'icon' => 'fal fa-bars',
                    ],

                ],
            ]
        );
    }
}
