<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Aug 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Enums\Production\Artisan\ArtisanPayTypeEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionActivityTypeEnum;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureStepTier;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateManufactureTaskSessionPay
{
    use AsAction;

    public function handle(ManufactureTaskSession $session): ManufactureTaskSession
    {
        $bands = ManufacturePayBand::effectiveAt($session->production_id, $session->ended_at)->get();
        $hours = round($session->paidHours(), 4);

        if ($hours <= 0) {
            $session->update($bands->isEmpty() ? ['hours' => 0] : ['hours' => 0, 'pay' => 0, 'bonus' => 0]);

            return $session;
        }

        $standardRate = $session->standard_rate !== null ? (float) $session->standard_rate : $session->recipeStandardRate();

        $unitsPerHour = null;
        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::PRODUCTION && $standardRate !== null) {
            $unitsPerHour = round($session->quantity_made / $hours, 0);
        }

        /* A production without pay bands (AWGp, HELP-4492) does not pay by speed: its output is still measured against the target. */
        if ($bands->isEmpty()) {
            $session->update(['hours' => $hours, 'units_per_hour' => $unitsPerHour]);

            return $session;
        }

        $band0       = $bands->firstWhere('code', '0');
        $editedTiers = $this->editedTiers($session, $standardRate);

        $band = $this->selectBand($session, $bands, $hours, $standardRate, $editedTiers['thresholds'] ?? []);
        if (!$band) {
            return $session;
        }

        $hourlyRate = (float) $band->hourly_rate;
        $pay        = round($hours * $hourlyRate, 2);
        $bonus      = $band0 ? max(0, round($pay - (float) $band0->hourly_rate * $hours, 2)) : 0;

        $session->update([
            'hours'           => $hours,
            'units_per_hour'  => $unitsPerHour,
            'pay_band_id'     => $band->id,
            'band_code'       => $band->code,
            'hourly_rate'     => $hourlyRate,
            'pay'             => $pay,
            'bonus'           => $bonus,
            'tier_thresholds' => $editedTiers,
        ]);

        return $session;
    }

    /**
     * Band thresholds edited on the artefact's step, or else on its family's, as they stood when the session started (HELP-4465).
     * ponytail: combined batches mix several recipes, so they keep the calculated thresholds.
     *
     * @return array{source: string, thresholds: array<string, float>}|null
     */
    public function editedTiers(ManufactureTaskSession $session, ?float $standardRate): ?array
    {
        if ($standardRate === null || $session->is_combined || $session->artisan_pay_type == ArtisanPayTypeEnum::HOURLY || ($session->activity_type && $session->activity_type != ManufactureTaskSessionActivityTypeEnum::PRODUCTION)) {
            return null;
        }

        $artefact = $session->jobOrderItemTask?->jobOrderItem?->artefact;
        if (!$artefact) {
            return null;
        }

        return ManufactureStepTier::inForce($artefact->id, $artefact->artefact_family_id, $session->manufacture_task_id, $session->started_at, $standardRate);
    }

    /**
     * @param  array<string, float>  $editedThresholds
     */
    private function selectBand(ManufactureTaskSession $session, Collection $bands, float $hours, ?float $standardRate, array $editedThresholds): ?ManufacturePayBand
    {
        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::DEVELOPMENT) {
            $developmentBand = $bands->firstWhere('code', $session->employee?->isGuruOn($session->ended_at) ? 'DG' : 'D');

            return $developmentBand ?? $bands->firstWhere('code', '0');
        }

        /* Machine cleaning is paid at band 2 whatever the artisan's pay type or performance (HELP-4441). */
        if ($session->activity_type == ManufactureTaskSessionActivityTypeEnum::MAINTENANCE) {
            return $bands->firstWhere('code', '2') ?? $bands->firstWhere('code', '0');
        }

        if ($session->artisan_pay_type == ArtisanPayTypeEnum::HOURLY) {
            return $bands->firstWhere('code', '0');
        }

        if ($session->activity_type != ManufactureTaskSessionActivityTypeEnum::PRODUCTION) {
            return $bands->firstWhere('code', '0');
        }

        if ($standardRate === null) {
            return $bands->firstWhere('code', '0');
        }

        $unitsPerHour = round($session->quantity_made / $hours, 0);

        $measuredBands = $bands
            ->filter(fn (ManufacturePayBand $band) => $band->target_multiplier !== null && $band->code != '0')
            ->sortBy('target_multiplier');

        $selected = $bands->firstWhere('code', '0');
        foreach ($measuredBands as $band) {
            $target = isset($editedThresholds[$band->code]) ? (float) $editedThresholds[$band->code] : $standardRate * (float) $band->target_multiplier;
            if ($unitsPerHour >= $target) {
                $selected = $band;
            }
        }

        return $selected;
    }
}
