<?php

/*
 * author Arya Permana - Kirin
 * created on 07-02-2025-11h-34m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\StoredItem\UI;

use App\Actions\Fulfilment\StoredItem\UI\GetStoredItemShowcase;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemMovements;
use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemPallets;
use App\Actions\Fulfilment\StoredItemAuditDelta\UI\IndexStoredItemAuditDeltas;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Retina\Fulfilment\StoredItems\UI\IndexRetinaStoredItems;
use App\Actions\RetinaAction;
use App\Enums\UI\Fulfilment\StoredItemTabsEnum;
use App\Http\Resources\Fulfilment\PalletsResource;
use App\Http\Resources\Fulfilment\StoredItemAuditDeltasResource;
use App\Http\Resources\Fulfilment\StoredItemMovementsResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Fulfilment\StoredItem;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property StoredItem $storedItem
 */
class ShowRetinaStoredItem extends RetinaAction
{
    public function authorize(ActionRequest $request): bool
    {
        return true; // TODO: @raul Permission
    }

    public function asController(StoredItem $storedItem, ActionRequest $request): StoredItem
    {
        $this->initialisation($request)->withTab(StoredItemTabsEnum::values());

        return $this->handle($storedItem);
    }

    public function handle(StoredItem $storedItem): StoredItem
    {
        return $storedItem;
    }

    public function htmlResponse(StoredItem $storedItem, ActionRequest $request): Response
    {
        return Inertia::render(
            'Storage/RetinaStoredItem',
            [
                'title' => __('stored item'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    request()->route()->originalParameters()
                ),
                'pageHead' => [
                    'icon' => [
                        'icon' => ['fal', 'fa-narwhal'],
                        'title' => __('Stored item'),
                    ],
                    'model' => 'stored item',
                    'title' => $storedItem->slug,
                ],
                'tabs' => [
                    'current' => $this->tab,
                    'navigation' => StoredItemTabsEnum::navigation(),
                ],

                StoredItemTabsEnum::SHOWCASE->value => $this->tab == StoredItemTabsEnum::SHOWCASE->value ?
                    fn () => GetStoredItemShowcase::run($storedItem)
                    : Inertia::lazy(fn () => GetStoredItemShowcase::run($storedItem)),

                StoredItemTabsEnum::PALLETS->value => $this->tab == StoredItemTabsEnum::PALLETS->value ?
                    fn () => PalletsResource::collection(IndexStoredItemPallets::run($storedItem, StoredItemTabsEnum::PALLETS->value))
                    : Inertia::lazy(fn () => PalletsResource::collection(IndexStoredItemPallets::run($storedItem, StoredItemTabsEnum::PALLETS->value))),

                StoredItemTabsEnum::AUDITS->value => $this->tab == StoredItemTabsEnum::AUDITS->value ?
                fn () => StoredItemAuditDeltasResource::collection(IndexStoredItemAuditDeltas::run($storedItem, prefix: StoredItemTabsEnum::AUDITS->value))
                : Inertia::lazy(fn () => StoredItemAuditDeltasResource::collection(IndexStoredItemAuditDeltas::run($storedItem, prefix: StoredItemTabsEnum::AUDITS->value))),

                StoredItemTabsEnum::MOVEMENTS->value => $this->tab == StoredItemTabsEnum::MOVEMENTS->value ?
                    fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($storedItem, StoredItemTabsEnum::MOVEMENTS->value))
                    : Inertia::lazy(fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($storedItem, StoredItemTabsEnum::MOVEMENTS->value))),

                StoredItemTabsEnum::HISTORY->value => $this->tab == StoredItemTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($storedItem, StoredItemTabsEnum::HISTORY->value))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($storedItem, StoredItemTabsEnum::HISTORY->value))),

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: StoredItemTabsEnum::HISTORY->value))
            ->table(IndexStoredItemAuditDeltas::make()->tableStructure($storedItem, StoredItemTabsEnum::AUDITS->value))
            ->table(IndexStoredItemMovements::make()->tableStructure($storedItem, StoredItemTabsEnum::MOVEMENTS->value))
            ->table(IndexStoredItemPallets::make()->tableStructure($storedItem, StoredItemTabsEnum::PALLETS->value));
    }

    public function jsonResponse(StoredItem $storedItem): StoredItemResource
    {
        return new StoredItemResource($storedItem);
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters, $suffix = ''): array
    {
        $headCrumb = function (StoredItem $storedItem, array $routeParameters, string $suffix) {
            return [
                [
                    'type' => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => $routeParameters['index'],
                            'label' => __('Stored Items'),
                        ],
                        'model' => [
                            'route' => $routeParameters['model'],
                            'label' => $storedItem->slug,
                        ],

                    ],
                    'suffix' => $suffix,
                ],
            ];
        };

        $storedItem = StoredItem::where('slug', $routeParameters['storedItem'])->first();

        return match ($routeName) {
            'retina.fulfilment.itemised_storage.stored_items.show' => array_merge(
                IndexRetinaStoredItems::make()->getBreadcrumbs(
                ),
                $headCrumb(
                    $storedItem,
                    [
                        'index' => [
                            'name' => 'retina.fulfilment.itemised_storage.stored_items.index',
                            'parameters' => $routeParameters,
                        ],
                        'model' => [
                            'name' => 'retina.fulfilment.itemised_storage.stored_items.show',
                            'parameters' => [
                                'storedItem' => $storedItem,
                            ],
                        ],
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }

    public function getPrevious(StoredItem $storedItem, ActionRequest $request): ?array
    {
        $previous = StoredItem::where('slug', '<', $storedItem->slug)->orderBy('id', 'desc')->first();

        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(StoredItem $storedItem, ActionRequest $request): ?array
    {
        $next = StoredItem::where('slug', '>', $storedItem->slug)->orderBy('id')->first();

        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?StoredItem $storedItem, string $routeName): ?array
    {
        if (! $storedItem) {
            return null;
        }

        return match ($routeName) {
            'retina.fulfilment.itemised_storage.stored_items.show' => [
                'label' => $storedItem->reference,
                'route' => [
                    'name' => $routeName,
                    'parameters' => [
                        'storedItem' => $storedItem->slug,
                    ],

                ],
            ],
        };
    }
}
