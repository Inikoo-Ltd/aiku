<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 13:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Marks a job left running past the shift as seen, so the Performance warning goes away.
 * It leaves shift_end_resolved_at alone: the job stays out of pay until its times are set with Edit times.
 */
class AcknowledgeManufactureTaskSessionShiftEnd extends OrgAction
{
    public function handle(ManufactureTaskSession $session, User $user): ManufactureTaskSession
    {
        $session->update([
            'shift_end_acknowledged_at' => now(),
            'shift_end_acknowledged_by' => $user->id,
        ]);

        return $session;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_operations.{$this->production->id}.orchestrate",
            "accounting.{$this->organisation->id}.edit",
        ]);
    }

    public function action(ManufactureTaskSession $session, User $user): ManufactureTaskSession
    {
        $this->asAction = true;
        $this->initialisationFromProduction($session->production, []);

        return $this->handle($session, $user);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
