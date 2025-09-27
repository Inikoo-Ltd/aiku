<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-13h-15m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceCategory\UI;

use App\Actions\OrgAction;
use App\Enums\Accounting\InvoiceCategory\InvoiceCategoryTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\SysAdmin\Organisation;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\LaravelOptions\Options;

class EditInvoiceCategory extends OrgAction
{
    public function handle(InvoiceCategory $invoiceCategory, ActionRequest $request): Response
    {
        return Inertia::render(
            'EditModel',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $invoiceCategory,
                    $request->route()->getName(),
                    $request->route()->originalParameters()
                ),
                'title'    => __('Edit invoice category'),
                'pageHead' => [
                    'title'        => __('Edit invoice category'),
                    'actions'      => [
                        [
                            'type'  => 'button',
                            'style' => 'cancel',
                            'label' => __('Cancel'),
                            'route' => [
                                'name'       => 'grp.org.accounting.invoice-categories.show',
                                'parameters' => array_values($request->route()->originalParameters())
                            ],
                        ]
                    ]
                ],
                'formData' => [
                    'fullLayout' => true,
                    'blueprint'  =>
                        [
                            [
                                'title'  => __('Edit Invoice Category'),
                                'fields' => [
                                    'name' => [
                                        'type'     => 'input',
                                        'label'    => __('name'),
                                        'value'   => $invoiceCategory->name,
                                        'required' => true,
                                    ],
                                    'type' => [
                                        'type'     => 'select',
                                        'label'    => __('type'),
                                        'value'    => $invoiceCategory->type,
                                        'options'  => Options::forEnum(InvoiceCategoryTypeEnum::class),
                                        'required' => true,
                                    ],
                                ]
                            ]
                        ],
                        'args'      => [
                            'updateRoute' => [
                                'name'       => 'grp.models.invoice-category.update',
                                'parameters' => [
                                    'invoiceCategory' => $invoiceCategory->id,
                                ]
                            ]
                        ]
                ],

            ]
        );
    }

    public function asController(Organisation $organisation, InvoiceCategory $invoiceCategory, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);

        return $this->handle($invoiceCategory, $request);
    }

    public function getBreadcrumbs(InvoiceCategory $invoiceCategory, string $routeName, array $routeParameters): array
    {
        return ShowInvoiceCategory::make()->getBreadcrumbs(
            invoiceCategory: $invoiceCategory,
            routeName: preg_replace('/edit$/', 'show', $routeName),
            routeParameters: $routeParameters,
            suffix: '('.__('Editing').')'
        );
    }
}
