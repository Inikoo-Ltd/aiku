<?php

/*
 * Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
 * Created: Tue, 14 Mar 2023 09:31:03 Central European Standard Time, Malaga, Spain
 * Copyright (c) 2023, Inikoo LTD
 */

namespace App\Actions\Procurement\PurchaseOrder\UI;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditPurchaseOrder extends OrgAction
{
    use WithProcurementEditAuthorisation;
    use WithAgentOrganisation;

    private bool $actingAsAgent = false;

    public function handle(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        return $purchaseOrder;
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): PurchaseOrder
    {
        $this->initialisation($organisation, $request);
        if ($purchaseOrder->organisation_id !== $organisation->id) {
            abort_unless($this->agentEditsOwnOrder($purchaseOrder, $request->user()), 404);
            $this->actingAsAgent = true;
        }

        return $this->handle($purchaseOrder);
    }

    public function htmlResponse(PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('purchase order'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $purchaseOrder,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => $purchaseOrder->reference,
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => 'grp.org.procurement.purchase_orders.show',
                                'parameters' => [$this->organisation->slug, $purchaseOrder->slug]
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => $this->actingAsAgent ? $this->agentSections($purchaseOrder) : [
                        [
                            'label'  => __('Reference'),
                            'title'  => __('Reference'),
                            'icon'   => 'fal fa-fingerprint',
                            'fields' => [
                                'reference' => [
                                    'type'     => 'input',
                                    'label'    => __('reference'),
                                    'required' => true,
                                    'value'    => $purchaseOrder->reference
                                ],
                            ]
                        ],
                        [
                            'label'  => __('Delivery'),
                            'title'  => __('Delivery'),
                            'icon'   => 'fal fa-truck',
                            'fields' => [
                                'delivery_address' => [
                                    'type'        => 'textarea',
                                    'label'       => __('Deliver to'),
                                    'information' => __('The address the supplier ships the goods to, printed on the purchase order.'),
                                    'placeholder' => __('Leave empty to use the organisation\'s delivery address from the procurement settings, or its warehouse address when that is not set'),
                                    'value'       => Arr::get($purchaseOrder->data, 'delivery_address'),
                                ],
                            ]
                        ],
                        ...$this->termsSections($purchaseOrder),
                        ...$this->cleanHandoverSections($purchaseOrder, $request),
                        [
                            'label'  => __('Payments'),
                            'title'  => __('Payments'),
                            'icon'   => 'fal fa-money-bill',
                            'fields' => [
                            ]
                        ]

                    ],
                    'args' => [
                        'updateRoute' => [
                            'name'      => 'grp.models.purchase-order.update',
                            'parameters' => $purchaseOrder->id

                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(PurchaseOrder $purchaseOrder, string $routeName, array $routeParameters): array
    {
        return ShowPurchaseOrder::make()->getBreadcrumbs(
            purchaseOrder: $purchaseOrder,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }

    /**
     * What an agent records on an order placed through it.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agentSections(PurchaseOrder $purchaseOrder): array
    {
        return [
            ...array_values(array_filter(
                $this->termsSections($purchaseOrder),
                fn (array $section) => Arr::has($section, 'fields.estimated_production_date')
            )),
            [
                'label'  => __('Clean handover'),
                'title'  => __('Clean handover'),
                'icon'   => 'fal fa-clipboard-list',
                'fields' => [
                    'agent_reference' => $this->agentReferenceField($purchaseOrder),
                ],
            ],
        ];
    }

    /**
     * The delivery, payment and label terms staff used to edit from the purchase order page.
     *
     * @return array<int, array<string, mixed>>
     */
    private function termsSections(PurchaseOrder $purchaseOrder): array
    {
        $icons = [
            'delivery_type'             => 'fal fa-truck-container',
            'estimated_production_date' => 'fal fa-calendar-check',
            'payment_terms'             => 'fal fa-file-invoice',
            'incoterm'                  => 'fal fa-file-signature',
            'terms_and_conditions'      => 'fal fa-tags',
        ];

        return collect(GetPurchaseOrderData::run($purchaseOrder)['blueprint'])
            ->map(fn (array $section) => [
                'label'  => $section['title'],
                'title'  => $section['title'],
                'icon'   => $icons[array_key_first($section['fields'])] ?? 'fal fa-info-circle',
                'fields' => Arr::except($section['fields'], ['reference', 'delivery_address']),
            ])
            ->filter(fn (array $section) => $section['fields'])
            ->values()
            ->all();
    }

    /**
     * An agent order's clean handover dates: the ready date is proposed by the buyer or the agent and approved by management, who also record compliance and any exclusion from the score.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * @return array<string, mixed>
     */
    private function agentReferenceField(PurchaseOrder $purchaseOrder): array
    {
        return [
            'type'        => 'input',
            'label'       => __('Agent\'s order number'),
            'information' => __('The agent\'s own number for this order, as it appears on their documents.'),
            'value'       => $purchaseOrder->agent_reference,
        ];
    }

    private function cleanHandoverSections(PurchaseOrder $purchaseOrder, ActionRequest $request): array
    {
        if (!$purchaseOrder->isAgentOrder()) {
            return [];
        }

        $fields = [
            'agent_reference' => $this->agentReferenceField($purchaseOrder),
        ];

        if ($request->user()->authorisedShopOrganisations()->exists()) {
            $fields += [
                'chs_excluded'           => [
                    'type'        => 'toggle',
                    'label'       => __('Exclude from clean handover score'),
                    'information' => __('Turn on to leave this order out of the agent\'s clean handover score, e.g. when the delay was our fault. Write why in the exclusion reason.'),
                    'value'       => $purchaseOrder->chs_excluded,
                ],
                'chs_exclusion_reason'   => [
                    'type'        => 'input',
                    'label'       => __('Exclusion reason'),
                    'information' => __('Why this order is left out of the clean handover score.'),
                    'value'       => $purchaseOrder->chs_exclusion_reason,
                ],
            ];
        }

        return [
            [
                'label'  => __('Clean handover'),
                'title'  => __('Clean handover'),
                'icon'   => 'fal fa-clipboard-list',
                'fields' => $fields,
            ],
        ];
    }
}
