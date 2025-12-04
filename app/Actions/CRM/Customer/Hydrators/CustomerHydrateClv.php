<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:32:25 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\Hydrators\WithHydrateInvoices;
use App\Actions\Traits\WithEnumStats;
use App\Models\CRM\Customer;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;

class CustomerHydrateClv implements ShouldBeUnique
{
    use WithEnumStats;
    use WithHydrateInvoices;
    use WithHydrateCommand;

    public string $jobQueue = 'low-priority';

    public string $commandSignature = 'hydrate:customers-clv {organisations?*} {--S|shop= shop slug} {--s|slug=}';

    public function __construct()
    {
        $this->model = Customer::class;
        $this->modelAsHandleArg = false;
    }

    public function getJobUniqueId(int $customerID): string
    {
        return $customerID;
    }


    public function handle(int $customerID): void
    {

        $customer = Customer::find($customerID);
        if (!$customer) {
            return;
        }

        // Check if customer has invoices in the last year
        $oneYearAgo = now()->subYear();
        $hasRecentInvoices = $customer->invoices()
            ->where('in_process', false)
            ->where('created_at', '>=', $oneYearAgo)
            ->exists();

        if (!$hasRecentInvoices) {
            $this->setDefaultStats($customer);
            return;
        }

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
            // Set default values if no invoices
            $this->setDefaultStats($customer);
            return;
        }

        // Convert to Carbon instances once
        $firstOrderDate = $invoiceStats->first_order_date ? Carbon::parse($invoiceStats->first_order_date) : null;
        $lastOrderDate  = $invoiceStats->last_order_date ? Carbon::parse($invoiceStats->last_order_date) : null;

        if (!$firstOrderDate || !$lastOrderDate) {
            $this->setDefaultStats($customer);
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
        $expectedRemainingLifespanMonths = $customerAgeMonths / $averageLifespanY;

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

            // 2. Historic CLV
            $historicClv = $averagePurchaseValue * $totalOrders;

            // 3. Predicted CLV for next year (12 months)
            $predictedClvNextYear = 12 * $averagePurchaseValue * $ordersPerMonth;

            // 4. Predicted CLV for remaining lifespan
            $predictedClvLifespan = $expectedRemainingLifespanMonths * $predictedClvNextYear;

            // 5. Total CLV
            $totalClv = $historicClv + $predictedClvLifespan;

            $stats['historic_clv_amount'.$suffix] = round($historicClv, 2);
            $stats['predicted_clv_amount_next_year'.$suffix] = round($predictedClvNextYear, 2);
            $stats['predicted_clv_amount'.$suffix] = round($predictedClvLifespan, 2);
            $stats['total_clv_amount'.$suffix] = round($totalClv, 2);
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

        // Calculate timeline positions
        $timelinePositions = $this->calculateTimelinePositions($firstOrderDate, $expectedNextOrder);

        // Final stats
        $stats['average_order_value'] = round($invoiceStats->total_net_amount / $totalOrders, 2);
        $stats['average_time_between_orders'] = $averageTimeBetweenOrders;
        $stats['expected_date_of_next_order'] = $expectedNextOrder;
        $stats['churn_interval'] = $churnInterval;
        $stats['churn_risk_prediction'] = round($churnRiskPrediction, 4);
        $stats['first_order_date'] = $firstOrderDate;
        $stats['expected_remaining_lifespan_months'] = round($expectedRemainingLifespanMonths);
        $stats['today_timeline_position'] = $timelinePositions['todayPosition'];
        $stats['next_order_timeline_position'] = $timelinePositions['nextOrderPosition'];

        // --- Update stats efficiently ---
        $customer->stats()->update($stats);
    }

    private function setDefaultStats(Customer $customer): void
    {
        $customer->stats()->update([
            'historic_clv_amount' => 0,
            'predicted_clv_amount_next_year' => 0,
            'predicted_clv_amount' => 0,
            'total_clv_amount' => 0,
            'average_order_value' => 0,
            'average_time_between_orders' => null,
            'expected_date_of_next_order' => null,
            'churn_interval' => 365,
            'churn_risk_prediction' => 0,
            'first_order_date' => null,
            'expected_remaining_lifespan_months' => 0,
            'today_timeline_position' => 0,
            'next_order_timeline_position' => null,
        ]);
    }

    private function calculateTimelinePositions(?Carbon $firstOrderDate, ?Carbon $nextOrderDate): array
    {
        $today = now();

        if (!$firstOrderDate) {
            return ['todayPosition' => 0, 'nextOrderPosition' => null];
        }

        // One year from today for a predicted period
        $oneYearFromNow = $today->copy()->addYear();

        // Calculate the total timeline range (from first order to 1 year from now)
        $totalRange = $oneYearFromNow->diffInSeconds($firstOrderDate);

        // Calculate positions as percentages
        $todayPosition = ($today->diffInSeconds($firstOrderDate) / $totalRange) * 100;
        $nextOrderPosition = $nextOrderDate ?
            min(100, ($nextOrderDate->diffInSeconds($firstOrderDate) / $totalRange) * 100) : null;

        return [
            'todayPosition' => max(0, min(100, $todayPosition)),
            'nextOrderPosition' => $nextOrderPosition ? max(0, min(100, $nextOrderPosition)) : null,
        ];
    }
}
