<?php

/*
 * author Arya Permana - Kirin
 * created on 09-01-2025-09h-25m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Pallet\UI;

use App\Actions\Fulfilment\StoredItem\UI\IndexStoredItemMovements;
use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\Retina\Fulfilment\StoredItems\UI\IndexRetinaStoredItems;
use App\Actions\Retina\Fulfilment\UI\ShowRetinaStorageDashboard;
use App\Actions\RetinaAction;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\UI\Fulfilment\PalletTabsEnum;
use App\Http\Resources\Fulfilment\RetinaPalletResource;
use App\Http\Resources\Fulfilment\StoredItemMovementsResource;
use App\Http\Resources\Fulfilment\StoredItemResource;
use App\Http\Resources\History\HistoryResource;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

/**
 * @property Pallet $pallet
 */
class ShowRetinaPallet extends RetinaAction
{
    public function asController(Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisation($request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisation($request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function inPalletReturn(PalletReturn $palletReturn, Pallet $pallet, ActionRequest $request): Pallet
    {
        $this->initialisation($request)->withTab(PalletTabsEnum::values());

        return $this->handle($pallet);
    }

    public function handle(Pallet $pallet): Pallet
    {
        return $pallet;
    }


    public function htmlResponse(Pallet $pallet, ActionRequest $request): Response
    {
        $icon = [
            'icon'    => ['fal', 'fa-pallet'],
            'tooltip' => __('Pallet')
        ];
        $model = __('Goods');
        $title = $this->pallet->reference;
        $iconRight = $pallet->status->statusIcon()[$pallet->status->value];
        $afterTitle = [];
        $actions = [
            [
                'type'    => 'button',
                'style'   => 'edit',
                'tooltip' => __('Edit pallet'),
                'label'   => __('Edit'),
                'route'   => [
                    'name'       => preg_replace('/show$/', 'edit', $request->route()->getName()),
                    'parameters' => array_values(request()->route()->originalParameters())
                ]
            ],
        ];

        if ($pallet->status == PalletStatusEnum::STORING) {
            $actions[] =
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('Return Goods'),
                    'route'   => [
                        'method'     => 'post',
                        'name'       => 'retina.models.pallet-return.store',
                        'parameters' => []
                    ]
                ];
        }

        if ($this->pallet->customer_reference) {
            $afterTitle = [
                'label'     => '(' . $this->pallet->customer_reference . ')'
            ];
        }

        $navigation = PalletTabsEnum::navigation($pallet);

        if (!$pallet->fulfilmentCustomer->items_storage) {
            unset($navigation[PalletTabsEnum::STORED_ITEMS->value]);
        }


        return Inertia::render(
            'Storage/RetinaPallet',
            [
                'title'                         => __('goods'),
                'breadcrumbs'                   => $this->getBreadcrumbs(
                    $pallet,
                    request()->route()->getName(),
                ),
                'navigation'                            => [
                    'previous' => $this->getPrevious($pallet, $request),
                    'next'     => $this->getNext($pallet, $request),
                ],
                'pageHead'                      => [
                    'icon'          => $icon,
                    'title'         => $title,
                    'model'         => $model,
                    'iconRight'     => $iconRight,
                    'noCapitalise'  => true,
                    'afterTitle'    => $afterTitle,
                    'actions'       => $actions,
                ],
                'tabs'                          => [
                    'current'    => $this->tab,
                    'navigation' => $navigation,
                ],
                PalletTabsEnum::SHOWCASE->value => $this->tab == PalletTabsEnum::SHOWCASE->value ?
                    fn () => $this->jsonResponse($pallet) : Inertia::lazy(fn () => $this->jsonResponse($pallet)),

                PalletTabsEnum::STORED_ITEMS->value => $this->tab == PalletTabsEnum::STORED_ITEMS->value ?
                    fn () => StoredItemResource::collection(IndexRetinaStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))
                    : Inertia::lazy(fn () => StoredItemResource::collection(IndexRetinaStoredItems::run($pallet, PalletTabsEnum::STORED_ITEMS->value))),

                PalletTabsEnum::MOVEMENTS->value => $this->tab == PalletTabsEnum::MOVEMENTS->value ?
                fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))
                : Inertia::lazy(fn () => StoredItemMovementsResource::collection(IndexStoredItemMovements::run($pallet, PalletTabsEnum::MOVEMENTS->value))),

                PalletTabsEnum::HISTORY->value => $this->tab == PalletTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($this->pallet))
                    : Inertia::lazy(fn () => HistoryResource::collection(IndexHistory::run($this->pallet)))

            ]
        )->table(IndexHistory::make()->tableStructure(prefix: PalletTabsEnum::HISTORY->value))
            ->table(IndexStoredItemMovements::make()->tableStructure($pallet, prefix: PalletTabsEnum::MOVEMENTS->value))
            ->table(IndexRetinaStoredItems::make()->tableStructure($pallet, prefix: PalletTabsEnum::STORED_ITEMS->value));
    }


    public function jsonResponse(Pallet $pallet): RetinaPalletResource
    {
        return new RetinaPalletResource($pallet);
    }


    public function getBreadcrumbs(Pallet $pallet, string $routeName, $suffix = null): array
    {
        return array_merge(
            ShowRetinaStorageDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'route' => [
                                'name'       => 'retina.fulfilment.storage.pallets.index',
                                'parameters' => array_values(request()->route()->originalParameters())
                            ],
                            'label' => __('Goods')
                        ],
                        'model' => [
                            'route' => [
                                'name'       => 'retina.fulfilment.storage.pallets.show',
                                'parameters' => [
                                    'pallet' => $pallet->slug
                                ]
                            ],
                            'label' => $pallet->reference,
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ]
        );
    }

    public function getPrevious(Pallet $pallet, ActionRequest $request): ?array
    {
        $previous = Pallet::where('id', '<', $pallet->id)
            ->where('fulfilment_customer_id', $request->user()->customer->fulfilmentCustomer->id)
            ->whereNotNull('slug')->orderBy('id', 'desc')->first();
        return $this->getNavigation($previous, $request->route()->getName());

    }

    public function getNext(Pallet $pallet, ActionRequest $request): ?array
    {
        $next = Pallet::where('id', '>', $pallet->id)
            ->where('fulfilment_customer_id', $request->user()->customer->fulfilmentCustomer->id)
            ->whereNotNull('slug')->orderBy('id')->first();
        return $this->getNavigation($next, $request->route()->getName());
    }

    private function getNavigation(?Pallet $pallet, string $routeName): ?array
    {
        if (!$pallet) {
            return null;
        }

        return match ($routeName) {
            'retina.fulfilment.storage.pallets.show' => [
                'label' => $pallet->slug,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'pallet'             => $pallet->slug
                    ]
                ]
            ],

            default => null,
        };
    }
}
