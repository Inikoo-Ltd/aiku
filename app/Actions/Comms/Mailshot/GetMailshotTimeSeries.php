<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Monday, 12 May 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Http\Resources\Comms\MailshotTimeSeriesResource;
use App\Models\Comms\Mailshot;
use App\Models\Comms\MailshotTimeSeries;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
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
            ->with(['records' => function ($query) use ($frequencyEnum) {
                $query->where('frequency', $frequencyEnum->value)
                    ->orderBy('from', 'asc');
            }])
            ->first();

        if (!$timeSeries) {
            return MailshotTimeSeriesResource::collection([]);
        }

        return MailshotTimeSeriesResource::collection($timeSeries->records);
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
