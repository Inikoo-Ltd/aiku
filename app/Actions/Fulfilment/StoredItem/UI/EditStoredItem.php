<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 May 2023 21:14:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\StoredItem\UI;

use App\Actions\OrgAction;
use App\Enums\UI\Fulfilment\StoredItemTabsEnum;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\Fulfilment\StoredItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStoredItem extends OrgAction
{
    private Warehouse|Organisation|FulfilmentCustomer|Fulfilment $parent;

    public function handle(StoredItem $storedItem): StoredItem
    {
        return $storedItem;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof FulfilmentCustomer) {
            $this->canEdit = $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.edit");

            return $request->user()->tokenCan('root') || $request->user()->authTo("fulfilment-shop.{$this->fulfilment->id}.view");
        } elseif ($this->parent instanceof Warehouse) {
            $this->canEdit       = $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.edit");
            return $request->user()->authTo("fulfilment.{$this->warehouse->id}.stored-items.view");
        }

        return false;
    }

    public function jsonResponse(LengthAwarePaginator $storedItems): AnonymousResourceCollection
    {
        return StoredItemResource::collection($storedItems);
    }


    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $this->parent,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'       => __("Customer's SKUs"),
                'pageHead'    => [
                    'model'     => __("Customer's SKU"),
                    'title'     => $storedItem->slug,
                    'icon'      => 'fal fa-narwhal',
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ]
                ],
                'formData' => [
                    'blueprint' => [
                        [
                            'label'  => __('Basic information'),
                            // 'icon'   => ['fal', 'fa-narwhal'],
                            'fields' => [
                                'reference' => [
                                    'type'    => 'input',
                                    'label'   => __('reference'),
                                    'value'   => $storedItem->reference,
                                    'required' => true
                                ],
                                'name' => [
                                    'type'    => 'input',
                                    'label'   => __('name'),
                                    'placeholder'   => __('Customer\'s SKU name'),
                                    'value'   => $storedItem->name,
                                    // 'required' => true
                                ],
                            ],
                        ]
                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stored-items.update',
                            'parameters' => $storedItem->id
                        ],
                    ]
                ],
            ]
        );
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->parent = $fulfilmentCustomer;
        $this->initialisationFromFulfilment($fulfilment, $request)->withTab(StoredItemTabsEnum::values());

        return $this->handle($storedItem);
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->parent = $warehouse;
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($storedItem);
    }

    public function getBreadcrumbs(Organisation|Warehouse|Fulfilment|FulfilmentCustomer $parent, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        return array_merge(
            ShowStoredItem::make()->getBreadcrumbs($parent, $routeParameters, '('.__('Editing').')'),
            []
        );
    }
}
