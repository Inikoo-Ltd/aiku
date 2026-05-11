<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Ordering\SalesChannel;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Ordering\SalesChannel;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoSalesChannelTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'sales-channels:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = SalesChannel::class;
    }

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_$to";
    }

    public function handle(?int $salesChannelId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$salesChannelId) {
            return;
        }

        $salesChannel = SalesChannel::find($salesChannelId);

        if (!$salesChannel) {
            return;
        }

        if (!$from || !$to) {
            $firstInvoicedDate = DB::connection('aiku_no_sticky')->table('invoices')->where('sales_channel_id', $salesChannel->id)->whereNull('deleted_at')->min('date');
            $lastInvoicedDate  = DB::connection('aiku_no_sticky')->table('invoices')->where('sales_channel_id', $salesChannel->id)->whereNull('deleted_at')->max('date');

            if (!$firstInvoicedDate) {
                return;
            }

            $from = $from ?? Carbon::parse($firstInvoicedDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastInvoicedDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessSalesChannelTimeSeriesRecords::dispatch($salesChannel->id, $frequency, $from, $to)->onQueue('sales_slave_historic');
            } else {
                ProcessSalesChannelTimeSeriesRecords::run($salesChannel->id, $frequency, $from, $to);
            }
        }
    }


}
