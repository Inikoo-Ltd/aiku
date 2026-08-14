<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateManufactureTaskSessionPay
{
    use AsAction;

    public function handle(ManufactureTaskSession $session): ManufactureTaskSession
    {
        $bands = ManufacturePayBand::where('production_id', $session->production_id)
            ->where('effective_from', '<=', $session->ended_at)
            ->where(function ($query) use ($session) {
                $query->whereNull('effective_to')
                    ->orWhere('effective_to', '>', $session->ended_at);
            })
            ->get();

        if ($bands->isEmpty()) {
            return $session;
        }

        $hours = round(
            $session->started_at->diffInSeconds($session->ended_at) / 3600 - $session->break_minutes / 60,
            4
        );

        if ($hours <= 0) {
            return $session;
        }

        $band0 = $bands->firstWhere('code', '0');

        $band = $this->selectBand($session, $bands, $hours);
        if (!$band) {
            return $session;
        }

        $unitsPerHour = null;
        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::PRODUCTION && $session->manufactureTask?->standard_rate !== null) {
            $unitsPerHour = round($session->quantity_made / $hours, 0);
        }

        $hourlyRate = (float) $band->hourly_rate;
        $pay        = round($hours * $hourlyRate, 2);
        $bonus      = $band0 ? max(0, round($pay - (float) $band0->hourly_rate * $hours, 2)) : 0;

        $session->update([
            'hours'          => $hours,
            'units_per_hour' => $unitsPerHour,
            'pay_band_id'    => $band->id,
            'band_code'      => $band->code,
            'hourly_rate'    => $hourlyRate,
            'pay'            => $pay,
            'bonus'          => $bonus,
        ]);

        return $session;
    }

    private function selectBand(ManufactureTaskSession $session, Collection $bands, float $hours): ?ManufacturePayBand
    {
        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT) {
            return $bands->firstWhere('code', 'D') ?? $bands->firstWhere('code', 'DG') ?? $bands->firstWhere('code', '0');
        }

        if ($session->activity_type != ManufactureTaskSessionActivityTypeEnum::PRODUCTION) {
            return $bands->firstWhere('code', '0');
        }

        $standardRate = $session->manufactureTask?->standard_rate;
        if ($standardRate === null) {
            return $bands->firstWhere('code', '0');
        }

        $unitsPerHour = round($session->quantity_made / $hours, 0);

        $measuredBands = $bands
            ->filter(fn (ManufacturePayBand $band) => $band->target_multiplier !== null && $band->code != '0')
            ->sortBy('target_multiplier');

        $selected = $bands->firstWhere('code', '0');
        foreach ($measuredBands as $band) {
            $target = $standardRate * (float) $band->target_multiplier;
            if ($unitsPerHour >= $target) {
                $selected = $band;
            }
        }

        return $selected;
    }
}
