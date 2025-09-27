<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:25:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Fulfilment\Fulfilment;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class EditInvoice extends OrgAction
{
    public function handle(Invoice $invoice): Invoice
    {
        return $invoice;
    }


    public function asController(Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($invoice->shop, $request);

        return $this->handle($invoice);
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inFulfilmentCustomer(Organisation $organisation, Fulfilment $fulfilment, FulfilmentCustomer $fulfilmentCustomer, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($invoice);
    }

    public function inOrganisation(Organisation $organisation, Invoice $invoice, ActionRequest $request): Invoice
    {
        $this->initialisation($organisation, $request);

        return $this->handle($invoice);
    }

    public function htmlResponse(Invoice $invoice, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'title'       => __('Edit invoice'),
                'breadcrumbs' => $this->getBreadcrumbs(
                    $invoice,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'pageHead'    => [
                    'title'     => __('Edit invoice'),
                    'container' => [
                        'icon'    => ['fal', 'fa-user'],
                        'tooltip' => __('Edit Invoice'),
                        'label'   => Str::possessive($invoice->reference)
                    ],
                    'actions'   => [
                        [
                            'type'  => 'button',
                            'style' => 'exitEdit',
                            'route' => [
                                'name'       => preg_replace('/edit$/', 'show', $request->route()->getName()),
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],

                'formData' => [
                    'blueprint' => [
                        [
                            'title'   => __('Footer'),
                            'label'   => __('Footer'),
                            'icon'    => 'fa-light fa-key',
                            'current' => true,
                            'fields'  => [
                                'footer' => [
                                    'type'  => 'textEditor',
                                    'label' => __('footer'),
                                    'value' => $invoice->footer
                                ],
                            ],
                        ],
                        [
                            'title'   => __('Reference'),
                            'label'   => __('Reference'),
                            'icon'    => 'fa-light fa-fingerprint',
                            'fields'  => [
                                'reference' => [
                                    'type'  => 'input',
                                    'label' => __('reference'),
                                    'value' => $invoice->reference
                                ],
                            ],
                        ],
                    ],
                    'args'      => [
                        'updateRoute' => [
                            'name'       => 'grp.models.invoice.update',
                            'parameters' => [$invoice->id]
                        ],
                    ]
                ]
            ]
        );
    }

    public function getBreadcrumbs(Invoice $invoice, string $routeName, array $routeParameters): array
    {
        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            return ShowRefund::make()->getBreadcrumbs(
                $invoice,
                routeName: 'grp.org.accounting.refunds.show',
                routeParameters: [
                    'organisation' => $invoice->organisation->slug,
                    'refund' => $invoice->slug
                ],
                suffix: '('.__('Editing').')'
            );
        }

        return ShowInvoice::make()->getBreadcrumbs(
            $invoice,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
