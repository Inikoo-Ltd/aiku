<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebVital;

use App\Models\Web\CruxRecord;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebsitePageSpeedSummary
{
    use AsObject;

    public const int VISITOR_DAYS = 28;

    private const array DEVICES = ['phone', 'desktop'];

    public function handle(Website $website): array
    {
        return [
            'crux'     => $this->crux($website),
            'visitors' => $this->visitors($website),
        ];
    }

    private function crux(Website $website): ?array
    {
        $latestRecords = CruxRecord::where('website_id', $website->id)
            ->whereNull('webpage_id')
            ->whereIn('form_factor', self::DEVICES)
            ->orderByDesc('period_end')
            ->get()
            ->unique('form_factor')
            ->keyBy('form_factor');

        if ($latestRecords->isEmpty()) {
            return null;
        }

        return [
            'period_start' => $latestRecords->min('period_start')?->toDateString(),
            'period_end'   => $latestRecords->max('period_end')?->toDateString(),
            'devices'      => collect(self::DEVICES)->mapWithKeys(fn (string $device) => [
                $device => ($record = $latestRecords->get($device)) ? [
                    'lcp' => $record->lcp_p75,
                    'inp' => $record->inp_p75,
                    'cls' => $record->cls_p75 !== null ? (float) $record->cls_p75 : null,
                ] : null,
            ])->all(),
        ];
    }

    private function visitors(Website $website): ?array
    {
        $rows = DB::connection('aiku_no_sticky')->table('web_vital_samples')
            ->where('website_id', $website->id)
            ->where('created_at', '>=', now()->subDays(self::VISITOR_DAYS)->startOfDay())
            ->whereIn('device', self::DEVICES)
            ->groupBy('device')
            ->havingRaw('COUNT(*) >= ?', [GetWebVitalsReport::MIN_SAMPLES])
            ->select('device')
            ->selectRaw('COUNT(*) as samples')
            ->selectRaw('percentile_cont(0.75) within group (order by lcp) as lcp')
            ->selectRaw('percentile_cont(0.75) within group (order by inp) as inp')
            ->selectRaw('percentile_cont(0.75) within group (order by cls) as cls')
            ->get()
            ->keyBy('device');

        if ($rows->isEmpty()) {
            return null;
        }

        return [
            'days'    => self::VISITOR_DAYS,
            'devices' => collect(self::DEVICES)->mapWithKeys(fn (string $device) => [
                $device => ($row = $rows->get($device)) ? [
                    'samples' => (int) $row->samples,
                    'lcp'     => $row->lcp !== null ? (int) round($row->lcp) : null,
                    'inp'     => $row->inp !== null ? (int) round($row->inp) : null,
                    'cls'     => $row->cls !== null ? round((float) $row->cls, 3) : null,
                ] : null,
            ])->all(),
        ];
    }
}
