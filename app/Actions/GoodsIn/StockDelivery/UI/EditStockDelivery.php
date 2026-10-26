<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\StockDelivery\UI;

use App\Actions\GoodsIn\StockDelivery\UpdateStockDelivery;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\GoodsIn\StockDelivery;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditStockDelivery extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(StockDelivery $stockDelivery): StockDelivery
    {
        return $stockDelivery;
    }

    private bool $editedByAgent = false;

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->initialisation($organisation, $request);
        abort_unless($stockDelivery->organisation_id === $organisation->id && !$stockDelivery->isManagedByPartner(), 404);

        return $this->handle($stockDelivery);
    }

    /**
     * The agent of a container edits its shipping references and dates from its own organisation.
     */
    public function inAgent(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): StockDelivery
    {
        $this->editedByAgent = true;
        $this->initialisation($organisation, $request);
        abort_unless($stockDelivery->agent?->organisation_id === $organisation->id && !$stockDelivery->isManagedByPartner(), 404);

        return $this->handle($stockDelivery);
    }

    private function agentFields(StockDelivery $stockDelivery, array $fields): array
    {
        $fields = Arr::only($fields, UpdateStockDelivery::AGENT_FIELDS);

        if (!$stockDelivery->isStaged()) {
            unset($fields['reference'], $fields['delivery_type']);
        } elseif (isset($fields['reference'])) {
            $fields['reference']['label']       = __('Name');
            $fields['reference']['information'] = __('Letters, numbers, dashes and underscores.');
        }

        return $fields;
    }

    public function htmlResponse(StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $icons = [
            'delivery_type'             => 'fal fa-truck-container',
            'invoice_number'            => 'fal fa-file-invoice',
            'estimated_dispatched_date' => 'fal fa-calendar-alt',
            'shipper_reference'         => 'fal fa-ship',
            'incoterm'                  => 'fal fa-file-signature',
        ];

        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Stock Delivery'),
                'breadcrumbs' => ShowStockDelivery::make()->getBreadcrumbs($stockDelivery, $request->route()->originalParameters(), '('.__('Editing').')'),
                'pageHead'    => [
                    'title'   => $stockDelivery->reference,
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => $this->editedByAgent ? 'grp.org.agent.stock_deliveries.show' : 'grp.org.procurement.stock_deliveries.show',
                                'parameters' => [$this->organisation->slug, $stockDelivery->slug],
                            ],
                        ],
                    ],
                ],
                'formData'    => [
                    'blueprint' => collect(GetStockDeliveryData::run($stockDelivery)['blueprint'])
                        ->map(fn (array $section) => [
                            'label'  => $section['title'],
                            'title'  => $section['title'],
                            'icon'   => $icons[array_key_first($section['fields'])] ?? 'fal fa-info-circle',
                            'fields' => $this->editedByAgent ? $this->agentFields($stockDelivery, $section['fields']) : $section['fields'],
                        ])
                        ->filter(fn (array $section) => $section['fields'])
                        ->values()
                        ->all(),
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.stock-delivery.update',
                            'parameters' => $stockDelivery->id,
                        ],
                    ],
                ],
            ]
        );
    }
}
