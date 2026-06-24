<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\Customer;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\CRM\Customer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoCustomerTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'customers:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Customer::class;
    }

    public function getJobUniqueId(?int $customerId, string $from, string $to): string
    {
        if ($customerId == null) {
            $customerId = 'empty';
        }

        return $customerId.":{$from}_$to";
    }

    public function handle(?int $customerId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$customerId) {
            return;
        }

        $customer = Customer::find($customerId);

        if (!$customer) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoices')->where('customer_id', $customer->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoices')->where('customer_id', $customer->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessCustomerTimeSeriesRecords::dispatch($customer->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessCustomerTimeSeriesRecords::run($customer->id, $frequency, $from, $to);
            }
        }
    }
}
