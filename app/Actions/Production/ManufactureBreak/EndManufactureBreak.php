<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Sep 2026 14:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureBreak;

use App\Actions\OrgAction;
use App\Actions\Production\ManufactureTaskSession\CalculateManufactureTaskSessionBreakMinutes;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureBreak;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class EndManufactureBreak extends OrgAction
{
    /**
     * A break never runs past its planned length: a tablet that posts late (asleep, offline)
     * still records at most planned_minutes.
     */
    public function handle(ManufactureBreak $break): ManufactureBreak
    {
        return DB::transaction(function () use ($break) {
            $break = ManufactureBreak::lockForUpdate()->find($break->id);
            if ($break->ended_at) {
                return $break;
            }

            $endedAt = now()->min($break->plannedEndAt());
            $break->update([
                'ended_at' => $endedAt,
                'minutes'  => (int) round($break->started_at->diffInSeconds($endedAt) / 60),
            ]);

            $openSession = ManufactureTaskSession::where('user_id', $break->user_id)
                ->where('state', ManufactureTaskSessionStateEnum::OPEN)
                ->first();
            if ($openSession) {
                CalculateManufactureTaskSessionBreakMinutes::run($openSession, now());
            }

            return $break;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->id == $this->break->user_id;
    }

    private ManufactureBreak $break;

    public function action(ManufactureBreak $break): ManufactureBreak
    {
        $this->asAction = true;
        $this->break    = $break;
        $this->initialisationFromProduction($break->production, []);

        return $this->handle($break);
    }

    public function asController(ManufactureBreak $manufactureBreak, ActionRequest $request): ManufactureBreak
    {
        $this->break = $manufactureBreak;
        $this->initialisationFromProduction($manufactureBreak->production, $request);

        return $this->handle($manufactureBreak);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
