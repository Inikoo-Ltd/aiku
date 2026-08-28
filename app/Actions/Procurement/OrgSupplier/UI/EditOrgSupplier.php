<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\SupplyChain\Supplier\UI\WithSupplierEditFields;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditOrgSupplier extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithSupplierEditFields;

    public function handle(OrgSupplier $orgSupplier): OrgSupplier
    {
        return $orgSupplier;
    }

    public function asController(Organisation $organisation, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inOrgAgent(Organisation $organisation, OrgAgent $orgAgent, OrgSupplier $orgSupplier, ActionRequest $request): OrgSupplier
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplier);
    }

    protected function ownsSupplierAgent(OrgSupplier $orgSupplier): bool
    {
        return (bool) ($orgSupplier->supplier->agent && $orgSupplier->supplier->agent->organisation_id === $this->organisation->id);
    }

    protected function canEditSupplier(OrgSupplier $orgSupplier): bool
    {
        return !$orgSupplier->supplier->agent_id || $this->ownsSupplierAgent($orgSupplier);
    }

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $supplier = $orgSupplier->supplier;

        $blueprint = $this->canEditSupplier($orgSupplier)
            ? $this->supplierEditSections($supplier)
            : [
                [
                    'title' => __('Status'),
                    'icon' => 'fa-light fa-cog',
                    'fields' => [
                        'status' => [
                            'type' => 'toggle',
                            'label' => __('Active'),
                            'value' => $orgSupplier->status,
                        ],
                    ],
                ],
            ];

        $updateRoute = $this->canEditSupplier($orgSupplier)
            ? [
                'name' => 'grp.models.supplier.update',
                'parameters' => $supplier->id,
            ]
            : [
                'name' => 'grp.models.org_supplier.update',
                'parameters' => $orgSupplier->id,
            ];

        return Inertia::render(
            'EditModel',
            [
                'title' => __('Edit supplier'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead' => [
                    'title' => $supplier->code,
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name' => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters()),
                            ],
                        ],
                    ],
                ],

                'formData' => [
                    'blueprint' => $blueprint,
                    'args' => [
                        'updateRoute' => $updateRoute,
                    ],
                ],
            ]
        );
    }

    public function getBreadcrumbs(string $routeName, array $routeParameters): array
    {
        return ShowOrgSupplier::make()->getBreadcrumbs(
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
