<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 13:00:00 Central European Standard Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Incoming;

use App\Actions\Dispatching\ProductionOutput\GetFinishedProductionJobOrders;
use App\Actions\Dispatching\ProductionOutput\IndexProductionOutputItems;
use App\Actions\OrgAction;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\Resources\Json\JsonResource;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class IndexIncomingProductionOutput extends OrgAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse, ?string $destination = null): array
    {
        $trips = GetFinishedProductionJobOrders::run($warehouse);

        return $destination ? array_values(array_filter($trips, fn (array $trip) => $trip['destination']['type'] === $destination)) : $trips;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(["incoming.{$this->warehouse->id}.view", "dispatching.{$this->warehouse->id}.view"]);
    }

    public function rules(): array
    {
        return [
            'destination' => ['sometimes', 'nullable', 'in:stock,partner'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->get('destination'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $trips
     */
    public function htmlResponse(array $trips, ActionRequest $request): Response
    {
        $title = match ($this->get('destination')) {
            'stock'   => __('From production, to stock'),
            'partner' => __('From production, to partner bays'),
            default   => __('From production'),
        };

        return Inertia::render(
            'Org/Incoming/IncomingProductionOutput',
            [
                'breadcrumbs'       => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'             => $title,
                'pageHead'          => [
                    'icon'  => ['icon' => ['fal', 'fa-industry'], 'title' => __('From production')],
                    'title' => $title,
                ],
                'production_output' => JsonResource::collection(IndexProductionOutputItems::run($trips, 'production_output')),
                'can_edit'          => $request->user()->authTo(["incoming.{$this->warehouse->id}.edit", "dispatching.{$this->warehouse->id}.edit"]),
                'put_away_route'    => [
                    'name'       => 'grp.org.warehouses.show.incoming.production_output.put_away',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ]
        )->table(IndexProductionOutputItems::make()->tableStructure('production_output'));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowIncomingHub::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.incoming.production_output.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('From production'),
                    ],
                ],
            ]
        );
    }
}
