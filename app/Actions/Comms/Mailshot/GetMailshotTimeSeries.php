<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\MailshotTimeSeries\ProcessMailshotTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Comms\MailshotTimeSeriesResource;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotTimeSeries;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;

class GetMailshotTimeSeries
{
    use AsAction;

    public function initialisationFromShop(Shop $shop, ActionRequest $request): void
    {
        // Initialize shop context if needed
    }

    public function handle(Mailshot $mailshot, ?string $frequency = null): AnonymousResourceCollection
    {
        $frequencyEnum = TimeSeriesFrequencyEnum::tryFrom($frequency) ?? TimeSeriesFrequencyEnum::DAILY;

        $timeSeries = MailshotTimeSeries::where('mailshot_id', $mailshot->id)
            ->where('frequency', $frequencyEnum)
            ->first();

        if (!$timeSeries) {
            // For DAILY frequency, create time series from start_sending_at to current date
            if ($frequencyEnum === TimeSeriesFrequencyEnum::DAILY) {
                $timeSeries = $this->createDailyTimeSeries($mailshot);
            } else {
                return MailshotTimeSeriesResource::collection([]);
            }
        }

        return MailshotTimeSeriesResource::collection($timeSeries->records);
    }

    protected function createDailyTimeSeries(Mailshot $mailshot): ?MailshotTimeSeries
    {
        // Determine the start date from start_sending_at, created_at, or date
        $startDate = $mailshot->start_sending_at
            ?? $mailshot->created_at
            ?? $mailshot->date;

        if (!$startDate) {
            return null;
        }

        $from = Carbon::parse($startDate)->format('Y-m-d');
        $to = now()->format('Y-m-d');

        // Process the time series records
        ProcessMailshotTimeSeriesRecords::run(
            mailshotId: $mailshot->id,
            frequency: TimeSeriesFrequencyEnum::DAILY,
            from: $from,
            to: $to
        );

        // Return the newly created time series
        return MailshotTimeSeries::where('mailshot_id', $mailshot->id)
            ->where('frequency', TimeSeriesFrequencyEnum::DAILY)
            ->first();
    }

    public function asController(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromShop($shop, $request);
        $frequency = $request->input('frequency');

        return $this->handle($mailshot, $frequency);
    }

    public function __invoke(Organisation $organisation, Shop $shop, Mailshot $mailshot, ActionRequest $request): AnonymousResourceCollection
    {
        $this->initialisationFromShop($shop, $request);
        $frequency = $request->input('frequency');

        return $this->handle($mailshot, $frequency);
    }
}
