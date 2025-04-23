<?php

/*
 * author Arya Permana - Kirin
 * created on 18-02-2025-10h-21m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Accounting\InvoiceCategory;

use App\Models\Accounting\InvoiceCategory;

trait WithInvoiceCategorySubNavigation
{
    protected function getInvoiceCategoryNavigation(InvoiceCategory $invoiceCategory): array
    {
        return [
            [
                "isAnchor" => true,
                "label"    => __($invoiceCategory->name),
                "route"     => [
                    "name"       => 'grp.org.accounting.invoice-categories.show',
                    "parameters" => [
                        'organisation' => $invoiceCategory->organisation->slug,
                        'invoiceCategory' => $invoiceCategory->slug
                    ],
                ],
            ],
            [
                "label"    => __('Invoices'),
                "route"     => [
                    "name"       => 'grp.org.accounting.invoice-categories.show.invoices.index',
                    "parameters" => [
                        'organisation' => $invoiceCategory->organisation->slug,
                        'invoiceCategory' => $invoiceCategory->slug
                    ],
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-file-invoice-dollar'],
                    'tooltip' => __('Invoices')
                ]
            ],
            [
                "label"    => __('Refunds'),
                "route"     => [
                    "name"       => 'grp.org.accounting.invoice-categories.show.refunds.index',
                    "parameters" => [
                        'organisation' => $invoiceCategory->organisation->slug,
                        'invoiceCategory' => $invoiceCategory->slug
                    ],
                ],
                'leftIcon' => [
                    'icon'    => ['fal', 'fa-arrow-circle-left'],
                    'tooltip' => __('Refunds')
                ]
            ],

            // [
            //     "number"   => $numberUnpaid,
            //     "label"    => __("Unpaid"),
            //     'tooltip'  => __("Show only unpaid invoices"),
            //     "route"     => [
            //         "name"       => $unpaidRouteName,
            //         "parameters" => $param,
            //     ],
            // ],
        ];
    }
}
