<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Sept 2024 21:06:37 Malaysia Time, Plane KL - Taipei
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Accounting\Invoice\InvoicePayStatusEnum;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\InvoiceCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerClient;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

trait WithHydrateInvoices
{
    public function getInvoicesStats(Group|Organisation|Shop|Customer|InvoiceCategory|CustomerClient $model): array
    {
        $numberInvoices = $model->invoices()->where('invoices.in_process', false)->count();
        $stats          = [
            'number_invoices'              => $numberInvoices,
            'number_invoices_type_invoice' => $model->invoices()->where('invoices.in_process', false)->where('type', InvoiceTypeEnum::INVOICE)->count(),
            'last_invoiced_at'             => $model->invoices()->where('invoices.in_process', false)->max('date'),
        ];

        if ($model instanceof Customer) {
            $stats['sales_all'] = $model->invoices()->where('invoices.in_process', false)->sum('net_amount');
            $stats['sales_org_currency_all'] = $model->invoices()->where('invoices.in_process', false)->sum('org_net_amount');
            $stats['sales_grp_currency_all'] = $model->invoices()->where('invoices.in_process', false)->sum('grp_net_amount');

        }

        if ($model instanceof InvoiceCategory) {
            $stats['number_invoiced_customers'] = $model->invoices()->where('invoices.in_process', false)->distinct('customer_id')->count('customer_id');
        }

        // unpaid hydrate
        $unpaidQuery = $model->invoices()->where('type', InvoiceTypeEnum::INVOICE)->where('invoices.in_process', false)->where('pay_status', InvoicePayStatusEnum::UNPAID);

        if ($model instanceof Customer || $model instanceof Shop) {
            $stats = array_merge($stats, [
                'number_unpaid_invoices' => $unpaidQuery->count(),
                'unpaid_invoices_amount' => $unpaidQuery->sum('total_amount'),
                'unpaid_invoices_amount_org_currency' => $unpaidQuery->sum('org_net_amount'),
                'unpaid_invoices_amount_grp_currency' => $unpaidQuery->sum('grp_net_amount'),
            ]);
        } elseif ($model instanceof Organisation) {
            $stats = array_merge($stats, [
                'number_unpaid_invoices' => $unpaidQuery->count(),
                'unpaid_invoices_amount_org_currency' => $unpaidQuery->sum('org_net_amount'),
                'unpaid_invoices_amount_grp_currency' => $unpaidQuery->sum('grp_net_amount'),
            ]);
        } elseif ($model instanceof Group) {
            $stats = array_merge($stats, [
                'number_unpaid_invoices' => $unpaidQuery->count(),
                'unpaid_invoices_amount_grp_currency' => $unpaidQuery->sum('grp_net_amount'),
            ]);
        }


        $stats['number_invoices_type_refund'] = $stats['number_invoices'] - $stats['number_invoices_type_invoice'];

        return $stats;
    }
}
