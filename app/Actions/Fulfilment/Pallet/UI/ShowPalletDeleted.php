<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\Fulfilment\UI\ShowFulfilment;
use App\Actions\Fulfilment\FulfilmentCustomer\ShowFulfilmentCustomer;
use App\Actions\Fulfilment\StoredItem\UI\IndexPalletStoredItems;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemMovements;
use App\Actions\Fulfilment\WithFulfilmentCustomerSubNavigation;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\UI\Fulfilment\ShowWarehouseFulfilmentDashboard;
use App\Enums\UI\Fulfilment\PalletTabsEnum;
use App\Http\Resources\Fulfilment\PalletResource;
use App\Http\Resources\Fulfilment\PalletStoredItemsResource;
use App\Http\Resources\Fulfilment\StoredItemMovementsResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Pallet $pallet
 */
class ShowPalletDeleted extends OrgAction
{
    use WithFulfilmentCustomerSubNavigation;

    public Customer|null $customer = null;
    private Warehouse|Organisation|FulfilmentCustomer|Fulfilment $parent;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            $this->canEdit = $request->user()->authTo("fulfilment.{$this->fulfilment->id}.stored-items.edit");

            return $request->user()->authTo("fulfilment.{$this->fulfilment->id}.stored-items.view");
        } elseif ($this->parent instanceof Warehouse) {
            $this->canEdit       = $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.edit");

            return $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.view");
        }

        $this->canEdit = $request->user()->authTo("fulfilment.{$this->organisation->id}.stored-items.edit");

        return $request->user()->authTo("fulfilment.{$this->organisation->id}.stored-items.view");
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, $palletSlug, ActionRequest $request): Pallet
    {
        $pallet = Pallet::onlyTrashed()->where('slug', $palletSlug)->first();
        if (!$pallet) {
            abort(404);
        }
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function handle(Pallet $pallet): Pallet
    {
        return $pallet;
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): Response
    {
        $icon       = [
            'icon'    => ['fal', 'fa-pallet'],
            'tooltip' => __('Pallet')
        ];
        $model      = __('Pallet');
        $title      = $pallet->reference;
        $iconRight  = $pallet->status->statusIcon()[$pallet->status->value];
        $afterTitle = [];
        if ($pallet->customer_reference) {
            $afterTitle = [
                'label' => '('.$pallet->customer_reference.')'
            ];
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            $icon                = [
                'icon'    => ['fal', 'fa-user'],
                'tooltip' => __('Customer')
            ];
            $model               = $this->parent->customer->name;
        }

        $subNavigation = [];
        $navigation    = PalletTabsEnum::navigation($pallet);

        if (!$pallet->fulfilmentCustomer->items_storage) {
            unset($navigation[PalletTabsEnum::STORED_ITEMS->value]);
        }

        if ($this->parent instanceof FulfilmentCustomer) {
            $subNavigation = $this->getFulfilmentCustomerSubNavigation($this->parent, $request);
        }

        $storedItemsList = array_map(function ($palletStoredItem) {
            return [
                'name' => $palletStoredItem->storedItem->name,
                'reference' => $palletStoredItem->storedItem->reference,
                'quantity' => (int) $palletStoredItem->quantity,
                'state' => $palletStoredItem->state
            ];
        }, $pallet->palletStoredItems->all());

        return Inertia::render(
            'Org/Fulfilment/Pallet',
            [
                'title'                         => __('pallets'),
                'breadcrumbs'                   => $this->getBreadcrumbs(
                    $this->parent,
                    request()->route()->originalParameters()
                ),
                'pageHead'                      => [
                    'icon'          => $icon,
                    'title'         => $title,
                    'model'         => $model,
                    'iconRight'     => $iconRight,
                    'noCapitalise'  => true,
                    'afterTitle'    => $afterTitle,
                    'subNavigation' => $subNavigation,
                ],
                'tabs'                          => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],

                'pallet'        => PalletResource::make($pallet),
                'list_stored_items'  => $storedItemsList,

                PalletTabsEnum::SHOWCASE->value => $this->tab == PalletTabsEnum::SHOWCASE->value ?
                    fn () => PalletResource::make($pallet) : Inertia::lazy(fn () => PalletResource::make($pallet)),

                PalletTabsEnum::STORED_ITEMS->value => $this->tab == PalletTabsEnum::STORED_ITEMS->value ?
                    fn () => PalletStoredItemsResource::collection(IndexPalletStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))
                    : Inertia::lazy(fn () => PalletStoredItemsResource::collection(IndexPalletStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))),

                PalletTabsEnum::MOVEMENTS->value => $this->tab == PalletTabsEnum::MOVEMENTS->value ?
                    fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))
                    : Inertia::lazy(fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))),

                PalletTabsEnum::HISTORY->value => $this->tab == PalletTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($pallet))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($pallet)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: PalletTabsEnum::HISTORY->value))
            ->table(IndexStoredItemMovements::make()->tableStructure($pallet, prefix: PalletTabsEnum::MOVEMENTS->value))
            ->table(IndexPalletStoredItems::make()->tableStructure($pallet, prefix: PalletTabsEnum::STORED_ITEMS->value));
    }


    public function jsonResponse(Pallet $pallet): PalletResource
    {
        return new PalletResource($pallet);
    }

    public function getBreadcrumbs(Organisation|Warehouse|Fulfilment|FulfilmentCustomer $parent, array $routeParameters, string $suffix = ''): array
    {
        $pallet = Pallet::withTrashed()->where('slug', $routeParameters['pallet'])->first();

        return match (class_basename($parent)) {
            'Warehouse' => $this->getBreadcrumbsFromWarehouse($pallet, $suffix),
            'Organisation', 'Fulfilment' => $this->getBreadcrumbsFromFulfilment($pallet, $suffix),
            default => $this->getBreadcrumbsFromFulfilmentCustomer($pallet, $suffix),
        };
    }

    public function getBreadcrumbsFromWarehouse(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowWarehouseFulfilmentDashboard::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallet')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.warehouses.show.inventory.pallets.current.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getBreadcrumbsFromFulfilment(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowFulfilment::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.current.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallets')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.operations.pallets.deleted.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         =>  __('Pallets'),
                ],
            ]
        );
    }

    public function getBreadcrumbsFromFulfilmentCustomer(Pallet $pallet, $suffix = null): array
    {
        return array_merge(
            ShowFulfilmentCustomer::make()->getBreadcrumbs(request()->route()->originalParameters()),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.pallets.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Pallets')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'grp.org.fulfilments.show.crm.customers.show.deleted_pallets.show',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => __('(🗑️ Deleted)'),
                ],
            ]
        );
    }
}
