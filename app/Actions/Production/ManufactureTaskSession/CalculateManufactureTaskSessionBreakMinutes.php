<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 14:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class CalculateManufactureTaskSessionBreakMinutes
{
    use AsAction;

    /**
     * Only the part of a break that overlaps the session window is deducted from its paid hours.
     */
    public function handle(ManufactureTaskSession $session, Carbon $until): ManufactureTaskSession
    {
        $seconds = ManufactureBreak::where('user_id', $session->user_id)
            ->where('started_at', '<', $until)
            ->where(fn ($query) => $query->whereNull('ended_at')->orWhere('ended_at', '>', $session->started_at))
            ->get()
            ->sum(fn (ManufactureBreak $break) => $break->secondsWithin($session->started_at, $until));

        $session->update(['break_minutes' => (int) round($seconds / 60)]);

        return $session;
    }
}
