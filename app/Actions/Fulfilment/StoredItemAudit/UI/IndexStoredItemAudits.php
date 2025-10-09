<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItemAudit\UI;

use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\Pallet\UI\ShowPallet;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithFulfilmentShopAuthorisation;
use App\Enums\Fulfilment\StoredItemAudit\StoredItemAuditScopeEnum;
use App\Http\Resources\Fulfilment\StoredItemAuditsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexStoredItemAudits extends OrgAction
{
    use WithFulfilmentShopAuthorisation;
    use WithFulfilmentCustomerSubNavigation;

    private FulfilmentCustomer|Fulfilment|Pallet $parent;

    public function asController(Organisation $organisation, Warehouse $warehouse, Fulfilment $fulfilment, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilment;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilment);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($fulfilmentCustomer);
    }
    /** @noinspection PhpUnusedParameterInspection */
    public function inPalletInFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Pallet $pallet, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $pallet;
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($pallet);
    }

    public function handle(FulfilmentCustomer|Fulfilment|Pallet $parent, $prefix = null): LengthAwarePaginator
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


        if ($parent instanceof FulfilmentCustomer) {
            $query->where('fulfilment_customer_id', $parent->id);
        } elseif ($parent instanceof Pallet) {
            $query->where('scope_type', StoredItemAuditScopeEnum::PALLET);
            $query->where('scope_id', $parent->id);
        } else {
            $query->where('fulfilment_id', $parent->id);
        }


        $query->defaultSort('stored_item_audits.date');


        return $query->allowedSorts(['state', 'reference','date','amount','tax_amount','total_amount','number_pallets','number_stored_items','number_added_stored_items','number_edited_stored_items','number_removed_stored_items'])
            ->allowedFilters([$globalSearch,  'reference'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

    }

    public function htmlResponse(LengthAwarePaginator $storedItemAudits, ActionRequest $request): Response
    {
        return Inertia::render(
            'Org/Fulfilment/StoredItemAudits',
            [
                'title'       => __('stored item audits'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'icon'    =>
                        [
                            'icon'  => ['fal', 'fa-narwhal'],
                            'title' => __('Stored item audit')
                        ],
                    'model'   => __('stored item'),
                    'title'   => __('stored item audits')

                ],
                'data'        => StoredItemAuditsResource::collection($storedItemAudits)


            ]
        )->table($this->tableStructure($this->parent));
    }

    public function tableStructure(
        FulfilmentCustomer|Fulfilment|Pallet $parent,
        ?array $modelOperations = null,
        $prefix = null,
        $canEdit = false
    ): Closure {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix, $canEdit) {
            if ($prefix) {
                $table->name($prefix)->pageName($prefix.'Page');
            }


            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->withEmptyState(
                    match (class_basename($parent)) {
                        'FulfilmentCustomer' => [
                            'title' => __("No audits found"),
                            'count' => $parent->number_stored_item_audits,
                        ],
                        'Fulfilment' => [
                            'title' => __("No audits found"),
                            'count' => $parent->stats->number_stored_item_audits,
                        ],
                        'Pallet' => [
                            'title' => __("No audits found"),
                            'count' => 0,
                        ],
                        default => null
                    }
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


    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Stored Item Audits'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.org.fulfilments.show.crm.customers.show.stored-items.index' =>
            array_merge(
                ShowFulfilmentCustomer::make()->getBreadcrumbs($routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            'grp.org.fulfilments.show.crm.customers.show.pallets.stored-item-audits.index' =>
            array_merge(
                ShowPallet::make()->getBreadcrumbs($this->parent->fulfilmentCustomer, $routeParameters),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),

            default => []
        };
    }
}
