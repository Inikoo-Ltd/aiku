<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateInvoices;
use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\Customer;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class CustomerHydrateClv implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;
    use WithHydrateInvoices;

    public string $commandSignature = 'hydrate:customer-clv {customer}';

    public function getJobUniqueId(int $customerId): string
    {
        return $customerId;
    }

    public function asCommand(Command $command): void
    {
        $customer = Customer::where('slug', $command->argument('customer'))->first();

        if (!$customer) {
            return;
        }

        $this->handle($customer);
    }

    public function handle(Customer $customer): void
    {
        // Aggregate invoice stats in a single, efficient SQL query
        $invoiceStats = $customer->invoices()
            ->where('in_process', false)
            ->selectRaw('
            COUNT(*) as total_orders,
            SUM(net_amount) as total_net_amount,
            SUM(org_net_amount) as total_org_net_amount,
            SUM(grp_net_amount) as total_grp_net_amount,
            MIN(created_at) as first_order_date,
            MAX(created_at) as last_order_date
            ')
            ->first();

        if (!$invoiceStats || $invoiceStats->total_orders == 0) {
            return; // No valid invoices to calculate CLV
        }

        // Convert to Carbon instances once
        $firstOrderDate = $invoiceStats->first_order_date ? Carbon::parse($invoiceStats->first_order_date) : null;
        $lastOrderDate  = $invoiceStats->last_order_date ? Carbon::parse($invoiceStats->last_order_date) : null;

        if (!$firstOrderDate || !$lastOrderDate) {
            return;
        }

        $totalOrders = (int)$invoiceStats->total_orders;
        $daysBetween = max($firstOrderDate->diffInDays($lastOrderDate), 1);

        // Average orders per month
        $ordersPerMonth = ($totalOrders / $daysBetween) * 30;

        // Customer age and lifespan
        $customerAgeDays  = (int)$customer->created_at->diffInDays(now());
        $averageLifespanY = max($customerAgeDays / 365, 1);
        $customerAgeMonths = max($customer->created_at->diffInMonths(now()), 1);
        $expectedRemainingLifespan = $customerAgeMonths / $averageLifespanY;

        $stats = [];

        // --- Calculate CLV values for each currency type ---
        $currencies = [
            ''               => $invoiceStats->total_net_amount,
            '_org_currency'  => $invoiceStats->total_org_net_amount,
            '_grp_currency'  => $invoiceStats->total_grp_net_amount,
        ];

        foreach ($currencies as $suffix => $totalRevenue) {
            if (!$totalRevenue) {
                $totalRevenue = 0;
            }

            // 1. Average Purchase Value
            $averagePurchaseValue = $totalRevenue / $totalOrders;

            // 2. Estimate Customer Value
            $customerValue = $customerAgeMonths * $averagePurchaseValue * $ordersPerMonth;

            // 3. Historic, Predicted, and Total CLV
            $historicClv  = $averagePurchaseValue * $totalOrders;
            $predictedClv = $expectedRemainingLifespan * $customerValue;
            $totalClv     = $historicClv + $predictedClv;

            $stats['historic_clv_amount'.$suffix]  = round($historicClv, 2);
            $stats['predicted_clv_amount'.$suffix] = round($predictedClv, 2);
            $stats['total_clv_amount'.$suffix]     = round($totalClv, 2);
        }

        // --- Currency-independent stats ---
        $averageTimeBetweenOrders = $totalOrders > 1
            ? ceil($daysBetween / ($totalOrders - 1))
            : null;

        $daysSinceLastOrder = $lastOrderDate->diffInDays(now());

        // Churn interval and risk prediction
        if ($averageTimeBetweenOrders && $averageTimeBetweenOrders > 0) {
            $churnInterval = max(($averageTimeBetweenOrders * 3) - $daysSinceLastOrder, 0);
            $churnRiskPrediction = min($daysSinceLastOrder / ($averageTimeBetweenOrders * 2), 1);
        } else {
            $churnInterval = 365;
            $churnRiskPrediction = 0;
        }

        // Predict next order date
        $expectedNextOrder = $averageTimeBetweenOrders
            ? $lastOrderDate->copy()->addDays($averageTimeBetweenOrders)
            : null;

        // Final stats
        $stats['average_order_value']         = round($invoiceStats->total_net_amount / $totalOrders, 2);
        $stats['average_time_between_orders'] = $averageTimeBetweenOrders;
        $stats['expected_date_of_next_order'] = $expectedNextOrder;
        $stats['churn_interval']              = $churnInterval;
        $stats['churn_risk_prediction']       = round($churnRiskPrediction, 4);

        // --- Update stats efficiently ---
        $customer->stats()->update($stats);
    }
}
