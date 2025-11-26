<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Tue, 25 Nov 2025 13:48:35 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Hydrators;

use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\Invoice;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;

trait WithHydrateSalesMetrics
{
    public function getSalesMetrics(array $params): array
    {
        $context = $params['context'];
        $start   = $params['start'];
        $end     = $params['end'];
        $fields  = $params['fields'] ?? [];

        $results = [];

        foreach ($fields as $field) {
            switch ($field) {

                case 'invoices':
                    $results['invoices'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::INVOICE)
                        ->whereBetween('date', [$start, $end])
                        ->count();
                    break;

                case 'refunds':
                    $results['refunds'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::REFUND)
                        ->whereBetween('date', [$start, $end])
                        ->count();
                    break;

                case 'orders':
                    $results['orders'] = Order::where($context)
                        ->whereBetween('date', [$start, $end])
                        ->count();
                    break;

                case 'registrations':
                    $results['registrations'] = Customer::where($context)
                        ->whereBetween('registered_at', [$start, $end])
                        ->count();
                    break;

                case 'baskets_created':
                    $results['baskets_created'] = Order::withTrashed()
                        ->where($context)
                        ->where('state', OrderStateEnum::CREATING)
                        ->whereBetween('date', [$start, $end])
                        ->sum('net_amount');
                    break;

                case 'baskets_created_grp_currency':
                    $results['baskets_created_grp_currency'] = Order::withTrashed()
                        ->where($context)
                        ->where('state', OrderStateEnum::CREATING)
                        ->whereBetween('date', [$start, $end])
                        ->sum('grp_net_amount');
                    break;

                case 'baskets_created_org_currency':
                    $results['baskets_created_org_currency'] = Order::withTrashed()
                        ->where($context)
                        ->where('state', OrderStateEnum::CREATING)
                        ->whereBetween('date', [$start, $end])
                        ->sum('org_net_amount');
                    break;

                case 'sales':
                    $results['sales'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->whereBetween('date', [$start, $end])
                        ->sum('net_amount');
                    break;

                case 'sales_grp_currency':
                    $results['sales_grp_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->whereBetween('date', [$start, $end])
                        ->sum('grp_net_amount');
                    break;

                case 'sales_org_currency':
                    $results['sales_org_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->whereBetween('date', [$start, $end])
                        ->sum('org_net_amount');
                    break;

                case 'revenue':
                    $results['revenue'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::INVOICE)
                        ->whereBetween('date', [$start, $end])
                        ->sum('net_amount');
                    break;

                case 'revenue_grp_currency':
                    $results['revenue_grp_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::INVOICE)
                        ->whereBetween('date', [$start, $end])
                        ->sum('grp_net_amount');
                    break;

                case 'revenue_org_currency':
                    $results['revenue_org_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::INVOICE)
                        ->whereBetween('date', [$start, $end])
                        ->sum('org_net_amount');
                    break;

                case 'lost_revenue':
                    $results['lost_revenue'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::REFUND)
                        ->whereBetween('date', [$start, $end])
                        ->sum('net_amount');
                    break;

                case 'lost_revenue_grp_currency':
                    $results['lost_revenue_grp_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::REFUND)
                        ->whereBetween('date', [$start, $end])
                        ->sum('grp_net_amount');
                    break;

                case 'lost_revenue_org_currency':
                    $results['lost_revenue_org_currency'] = Invoice::where($context)
                        ->where('in_process', false)
                        ->where('type', InvoiceTypeEnum::REFUND)
                        ->whereBetween('date', [$start, $end])
                        ->sum('org_net_amount');
                    break;
            }
        }

        return $results;
    }
}
