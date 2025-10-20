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

        $this->handle($customer->id);
    }

    public function handle(int $customerId): void
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        // Get customer's orders
        $invoices = $customer->invoices()->where('in_process', false)->get();

        if ($invoices->isEmpty()) {
            return; // No orders to calculate CLV
        }

        // Calculate for each currency type
        $currencies = ['', '_org_currency', '_grp_currency'];

        foreach ($currencies as $currencySuffix) {
            $amountColumn = 'net_amount';

            if ($currencySuffix === '_org_currency') {
                $amountColumn = 'org_net_amount';
            }

            if ($currencySuffix === '_grp_currency') {
                $amountColumn = 'grp_net_amount';
            }

            // 1. Calculate Average Purchase Value
            $totalRevenue         = $invoices->sum($amountColumn);
            $totalOrders          = $invoices->count();
            $averagePurchaseValue = $totalRevenue / $totalOrders;

            // 2. Calculate Average Purchase Frequency (orders per time period)
            $firstOrderDate          = $invoices->min('created_at');
            $lastOrderDate           = $invoices->max('created_at');
            $daysBetweenFirstAndLast = $firstOrderDate->diffInDays($lastOrderDate);

            $ordersPerMonth = $daysBetweenFirstAndLast > 0
                ? ($totalOrders / $daysBetweenFirstAndLast) * 30
                : $totalOrders;

            // 3. Calculate Average Customer Lifespan (in years)
            $customerAge = (int)$customer->created_at->diffInDays(now());


            $averageCustomerLifespan = $customerAge / 365;
            $monthlyCustomerLifespan = (int)$customer->created_at->diffInMonths(now());

            $expectedRemainingLifespan=0;
            if($monthlyCustomerLifespan>0) {
                $expectedRemainingLifespan = $monthlyCustomerLifespan / $averageCustomerLifespan;
            }

            $customerValue = $monthlyCustomerLifespan * $averagePurchaseValue * $ordersPerMonth;

            // 4. Calculate CLV values
            $historicClv  = $averagePurchaseValue * $totalOrders;
            $predictedClv = $expectedRemainingLifespan * $customerValue;
            $totalClv     = $historicClv + $predictedClv;

            // Store values with appropriate suffix
            $stats['historic_clv_amount'.$currencySuffix]  = round($historicClv, 2);
            $stats['predicted_clv_amount'.$currencySuffix] = round($predictedClv, 2);
            $stats['total_clv_amount'.$currencySuffix]     = round($totalClv, 2);
        }

        // Calculate average time between orders (only once, currency-independent)
        $firstOrderDate          = $invoices->min('date');
        $lastOrderDate           = $invoices->max('date');
        $daysBetweenFirstAndLast = $firstOrderDate->diffInDays($lastOrderDate);

        $averageTimeBetweenOrders = $daysBetweenFirstAndLast > 0
            ? ceil($daysBetweenFirstAndLast / max($invoices->count() - 1, 1))
            : null;

        // Calculate days since last order
        $daysSinceLastOrder = (int)$lastOrderDate->diffInDays(now());

        // Calculate churn interval (predicted days until customer churns)
        // Using a common formula: expected lifetime remaining based on purchase pattern
        if ($averageTimeBetweenOrders && $averageTimeBetweenOrders > 0) {
            // Churn interval = average time between orders * expected remaining order cycles
            // Common approach: use 3x the average time between orders as churn threshold
            $churnInterval = max(($averageTimeBetweenOrders * 3) - $daysSinceLastOrder, 0);
        } else {
            $churnInterval = 365; // default to 1 year if no pattern
        }

        // Calculate churn risk prediction (0-1 scale)
        $churnRiskPrediction = 0;
        if ($averageTimeBetweenOrders && $averageTimeBetweenOrders > 0) {
            // Risk increases as days since last order exceeds average time between orders
            $churnRiskPrediction = min($daysSinceLastOrder / ($averageTimeBetweenOrders * 2), 1);
        }

        // Predict next order date
        $expectedNextOrder = $averageTimeBetweenOrders
            ? $lastOrderDate->copy()->addDays($averageTimeBetweenOrders)
            : null;

        // Add currency-independent stats
        $stats['average_order_value']         = round($invoices->sum('net_amount') / $invoices->count(), 2);
        $stats['average_time_between_orders'] = $averageTimeBetweenOrders;
        $stats['expected_date_of_next_order'] = $expectedNextOrder;
        $stats['churn_interval']              = $churnInterval;
        $stats['churn_risk_prediction']       = round($churnRiskPrediction, 4);

        $customer->stats()->update($stats);
    }
}
