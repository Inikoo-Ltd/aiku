<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 10:21:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgSupplier\UI;

use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Actions\OrgAction;
use App\Actions\Procurement\WithProcurementSerialReferences;
use App\Actions\SupplyChain\Supplier\UI\WithSupplierEditFields;
use App\Models\Procurement\OrgAgent;
use App\Models\Procurement\OrgSupplier;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\AgentOrganisationSelection;
use Lorisleiva\Actions\ActionRequest;

class EditOrgSupplier extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithProcurementSerialReferences;
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

    public function htmlResponse(OrgSupplier $orgSupplier, ActionRequest $request): Response
    {
        $supplier = $orgSupplier->supplier;

        $orgSupplierUpdateRoute = [
            'name' => 'grp.models.org_supplier.update',
            'parameters' => $orgSupplier->id,
        ];

        $statusSection = [
            'label' => __('Status'),
            'title' => __('Status'),
            'icon' => 'fa-light fa-toggle-on',
            'fields' => [
                'status' => [
                    'type' => 'toggle',
                    'label' => __('Active'),
                    'information' => __('Inactive suppliers are hidden from the suppliers list'),
                    'value' => $orgSupplier->status,
                    'updateRoute' => $orgSupplierUpdateRoute,
                ],
            ],
        ];

        $selection           = AgentOrganisationSelection::fromRequest($request, 'supplier');
        $editsOrganisation   = !$selection || $request->user()->authTo("procurement.$orgSupplier->organisation_id.edit");
        $blueprint           = [...$this->supplierEditSections($supplier), ...($editsOrganisation ? $this->labelOrganisationSections($selection, [$statusSection]) : [])];
        $updateRoute = [
            'name' => 'grp.models.supplier.update',
            'parameters' => $supplier->id,
        ];

        if ($supplier->agent && $supplier->agent->organisation_id === $this->organisation->id) {
            $blueprint[] = $this->supplierAgentFeeSection($supplier);
        }

        if ($request->user()->authTo('supply-chain.edit')) {
            $blueprint[] = $this->supplierAgentSection($supplier);
        }

        if ($editsOrganisation) {
            $blueprint = array_merge($blueprint, $this->labelOrganisationSections($selection, $this->procurementSerialReferenceSections($orgSupplier, $orgSupplierUpdateRoute)));
        }

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
                    'model' => __('Supplier'),
                    'actions' => [
                        [
                            'type' => 'button',
                            'style' => 'exitEdit',
                            'route' => $selection
                                ? $selection->route('grp.org.procurement.org_suppliers.show', $request->route()->originalParameters())
                                : [
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

    /**
     * On the agent's one page per supplier, fields kept per buying organisation say whose they are: the selected
     * organisation's, or in All the first buying organisation's (AW). They are saved as that organisation, so only
     * who may edit its procurement gets them.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function labelOrganisationSections(?AgentOrganisationSelection $selection, array $sections): array
    {
        if (!$selection) {
            return $sections;
        }

        return array_map(fn (array $section) => [
            ...$section,
            'fields' => array_map(fn (array $field) => isset($field['label']) ? [...$field, 'label' => $selection->label($field['label'])] : $field, $section['fields'] ?? []),
        ], $sections);
    }
}
