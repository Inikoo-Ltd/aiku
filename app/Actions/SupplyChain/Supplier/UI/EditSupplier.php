<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\SupplyChain\Supplier\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Agent;
use App\Actions\Traits\UI\WithBucketNavigation;
use App\Models\SupplyChain\Supplier;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditSupplier extends OrgAction
{
    use WithBucketNavigation;
    use WithSupplierEditFields;

    use WithSupplyChainEditAuthorisation;
    // todo: authorisation

    public function handle(Supplier $supplier): Supplier
    {
        return $supplier;
    }


    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);
        return $this->handle($supplier);
    }


    /** @noinspection PhpUnusedParameterInspection */
    public function inAgent(Agent $agent, Supplier $supplier, ActionRequest $request): Supplier
    {
        $group = group();
        $this->initialisationFromGroup($group, $request);
        return $this->handle($supplier);
    }

    public function htmlResponse(Supplier $supplier, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $supplier,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'navigation'                              => [
                    'previous' => $this->getPrevious($supplier, $request),
                    'next'     => $this->getNext($supplier, $request),
                ],
                'pageHead'    => [
                    'title'     => $supplier->code,
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

                'formData'    => [
                    'blueprint' => $this->supplierEditSections($supplier),
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.supplier.update',
                            'parameters' => $supplier->id

                        ],
                    ]
                ],
            ]
        );
    }


    public function getBreadcrumbs(Supplier $supplier, string $routeName, array $routeParameters): array
    {
        return ShowSupplier::make()->getBreadcrumbs(
            supplier: $supplier,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    public function getPrevious(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getNeighbour($supplier, $request, forward: false), $request->route()->getName());
    }

    public function getNext(Supplier $supplier, ActionRequest $request): ?array
    {
        return $this->getNavigation($this->getNeighbour($supplier, $request, forward: true), $request->route()->getName());
    }

    private function getNeighbour(Supplier $supplier, ActionRequest $request, bool $forward): ?Supplier
    {
        $query = Supplier::query()->where('suppliers.group_id', $supplier->group_id);
        if ($request->route()->getName() == 'grp.supply-chain.agents.show.suppliers.edit') {
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
            forward: $forward
        );
    }

    private function getNavigation(?Supplier $supplier, string $routeName): ?array
    {
        if (!$supplier) {
            return null;
        }

        return match ($routeName) {
            'grp.supply-chain.suppliers.edit' => [
                'label' => $supplier->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'supplier'  => $supplier->slug
                    ]

                ]
            ],
            'grp.supply-chain.agents.show.suppliers.edit' => [
                'label' => $supplier->name,
                'route' => [
                    'name'      => $routeName,
                    'parameters' => [
                        'agent'     => $supplier->agent->slug,
                        'supplier'  => $supplier->slug
                    ]

                ]
            ]
        };
    }
}
