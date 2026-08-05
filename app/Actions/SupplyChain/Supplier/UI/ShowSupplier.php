<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Wed, 15 Mar 2023 14:15:00 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\Helpers\History\UI\IndexHistory;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\Agent\UI\ShowAgent;
use App\Actions\SupplyChain\Supplier\WithSupplierSubNavigation;
use App\Actions\SupplyChain\UI\ShowSupplyChainDashboard;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Enums\UI\SupplyChain\SupplierTabsEnum;
use App\Http\Resources\History\HistoryResource;
use App\Http\Resources\SupplyChain\SupplierResource;
use App\Models\SupplyChain\Agent;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplier extends OrgAction
{
    use WithBucketNavigation;
    use WithSupplierSubNavigation;
    use WithSupplyChainAuthorisation;

    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($supplier->group, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $this->initialisationFromGroup($supplier->group, $request)->withTab(SupplierTabsEnum::values());

        return $this->handle($supplier);
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'SupplyChain/Supplier',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'  => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next'     => $this->getNext($supplier, $request),
                ],
                'title'       => __('Supplier'),
                'pageHead'    => [
                    'title'         => $supplier->name,
                    'icon'          => [
                        'icon'  => 'fal fa-person-dolly',
                        'title' => __('Supplier'),
                    ],
                    'model'         => __('Supplier'),
                    'subNavigation' => $this->getSupplierNavigation($supplier),
                ],
                'tabs'        => [
                    'current'    => $this->tab,
                    'navigation' => SupplierTabsEnum::navigation(),
                ],

                SupplierTabsEnum::SHOWCASE->value => $this->tab == SupplierTabsEnum::SHOWCASE->value ?
                    fn () => GetSupplierShowcase::run($supplier)
                    : Inertia::optional(fn () => GetSupplierShowcase::run($supplier)),

                SupplierTabsEnum::HISTORY->value => $this->tab == SupplierTabsEnum::HISTORY->value ?
                    fn () => HistoryResource::collection(IndexHistory::run($supplier))
                    : Inertia::optional(fn () => HistoryResource::collection(IndexHistory::run($supplier))),
            ]
        )->table(IndexHistory::make()->tableStructure(prefix: SupplierTabsEnum::HISTORY->value));
    }

    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters, string $suffix = ''): array
    {
        $headCrumb = function (Supplier $supplier, array $routeParameters, string $suffix) {
            return [
                [
                    'type'           => 'modelWithIndex',
                    'modelWithIndex' => [
                        'index' => [
                            'label' => __('Suppliers'),
                            'route' => $routeParameters['index'],
                        ],
                        'model' => [
                            'label' => $supplier->name,
                            'route' => $routeParameters['model'],
                        ],
                    ],
                    'suffix'         => $suffix,
                ],
            ];
        };

        return match ($routeName) {
            'grp.supply-chain.suppliers.supplier_products.index',
            'grp.supply-chain.suppliers.show' =>
            array_merge(
                ShowSupplyChainDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.suppliers.index',
                            'parameters' => [],
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.suppliers.show',
                            'parameters' => [$supplier->slug],
                        ],
                    ],
                    $suffix,
                ),
            ),
            'grp.supply-chain.agents.show.suppliers.show' =>
            array_merge(
                ShowAgent::make()->getBreadcrumbs($supplier->agent, $routeName, $routeParameters),
                $headCrumb(
                    $supplier,
                    [
                        'index' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.index',
                            'parameters' => Arr::only($routeParameters, 'agent'),
                        ],
                        'model' => [
                            'name'       => 'grp.supply-chain.agents.show.suppliers.show',
                            'parameters' => $routeParameters,
                        ],
                    ],
                    $suffix,
                ),
            ),
            default => [],
        };
    }

    public function jsonResponse(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierNeighbour($supplier, $request, forward: false), $request->route()->getName());
    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getSupplierNeighbour($supplier, $request, forward: true), $request->route()->getName());
    }

    private function getSupplierNeighbour(Supplier $supplier, ActionRequest $request, bool $forward): ?Supplier
    {
        $query = Supplier::query()->where('suppliers.group_id', $supplier->group_id);

        if ($request->route()->getName() == 'grp.supply-chain.agents.show.suppliers.show') {
            $query->where('suppliers.agent_id', $supplier->agent_id);
        } elseif ($request->input('bucket') == 'free') {
            $query->whereNull('suppliers.agent_id');
        } elseif ($request->input('bucket') == 'in_agents') {
            $query->whereNotNull('suppliers.agent_id');
        }

        return $this->getBucketNeighbour(
            query: $query,
            model: $supplier,
            sort: $request->input('bucket_sort'),
            sortColumns: [
                'code' => 'suppliers.code',
                'name' => 'suppliers.name',
            ],
            defaultSort: ['suppliers.code', false],
            forward: $forward,
        );
    }

    private function getNavigation(?Supplier $supplier, string $routeName): ?array
    {
        if (!$supplier) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'supplier' => $supplier->slug,
                    ],
                ],
            ],
            'grp.supply-chain.agents.show.suppliers.show' => [
                'label' => $supplier->code,
                'route' => [
                    'name'       => $routeName,
                    'parameters' => [
                        'agent'    => $supplier->agent->slug,
                        'supplier' => $supplier->slug,
                    ],
                ],
            ],
            default => null,
        };
    }
}
