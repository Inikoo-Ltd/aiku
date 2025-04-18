<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-01-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Accounting\Invoice;

use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;

trait WithInvoicesSubNavigation
{
    protected function getInvoicesNavigation(Organisation|Shop|Fulfilment $parent): array
    {
        if ($parent instanceof Organisation) {
            $routeName             = 'grp.org.accounting.invoices';
            $param                 = [$parent->slug];
            $total                 = $parent->orderingStats?->number_invoices_type_invoice ?? 10;
            $numberUnpaid          = $parent->orderingStats?->number_unpaid_invoices ?? 0;
            $numberRefunds         = $parent->orderingStats?->number_invoices_type_refund ?? 0;
            $numberDeletedInvoices = $parent->orderingStats->number_deleted_invoices ?? 0;

            $allInvoicesRouteName     = $routeName.'.index';
            $refundsRouteName         = str_replace('invoices', 'refunds', $routeName).'.index';
            $unpaidRouteName          = str_replace('invoices', 'unpaid_invoices', $routeName).'.index';
            $deletedInvoicesRouteName = str_replace('invoices', 'deleted_invoices', $routeName).'.index';
        } elseif ($parent instanceof Fulfilment) {
            $total                    = $parent->shop->orderingStats?->number_invoices ?? 10;
            $routeName                = 'grp.org.fulfilments.show.operations.invoices';
            $param                    = [$parent->organisation->slug, $parent->slug];
            $numberUnpaid             = $parent->shop->orderingStats?->number_unpaid_invoices ?? 0;
            $numberRefunds            = $parent->shop->orderingStats?->number_invoices_type_refund ?? 0;
            $numberDeletedInvoices    = $parent->shop->orderingStats->number_deleted_invoices ?? 0;
            $allInvoicesRouteName     = $routeName.'.all.index';
            $refundsRouteName         = $routeName.'.refunds.index';
            $unpaidRouteName          = $routeName.'.unpaid_invoices.index';
            $deletedInvoicesRouteName = $routeName.'.deleted_invoices.index';
        } else {
            $total                    = $parent->orderingStats?->number_invoices ?? 10;
            $routeName                = 'grp.org.shops.show.dashboard.invoices';
            $param                    = [$parent->organisation->slug, $parent->slug];
            $numberUnpaid             = $parent->orderingStats?->number_unpaid_invoices ?? 0;
            $numberRefunds            = $parent->orderingStats?->number_invoices_type_refund ?? 0;
            $numberDeletedInvoices    = $parent->orderingStats->number_deleted_invoices ?? 0;
            $allInvoicesRouteName     = $routeName.'.index';
            $unpaidRouteName          = str_replace('invoices', 'invoices.unpaid', $routeName).'.index';
            $refundsRouteName         = str_replace('invoices', 'invoices.refunds', $routeName).'.index';
            $deletedInvoicesRouteName = str_replace('invoices', 'invoices.deleted', $routeName).'.index';
        }

        return [
            [
                "number"   => $total,
                "isAnchor" => true,
                "label"    => __('Invoices'),
                "route"    => [
                    "name"       => $allInvoicesRouteName,
                    "parameters" => $param,
                ],
            ],

            [
                "number"  => $numberUnpaid,
                "label"   => __("Unpaid"),
                'tooltip' => __("Show only unpaid invoices"),
                "route"   => [
                    "name"       => $unpaidRouteName,
                    "parameters" => $param,
                ],
            ],

            [
                "number" => $numberDeletedInvoices,
                "label"  => __("Deleted Invoices"),
                'align'  => 'right',
                "route"  => [
                    "name"       => $deletedInvoicesRouteName,
                    "parameters" => $param,
                ],
            ],
            [
                "number" => $numberRefunds,
                "label"  => __("Refunds"),
                'align'  => 'right',
                "route"  => [
                    "name"       => $refundsRouteName,
                    "parameters" => $param,
                ],
            ],
        ];
    }
}
