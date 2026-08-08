<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Aug 2026 13:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Actions\Production\JobOrderItemTask\CalculateJobOrderItemTaskQuantities;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufactureTaskSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class VoidManufactureTaskSession extends OrgAction
{
    public function handle(ManufactureTaskSession $session): ManufactureTaskSession
    {
        if ($session->state != ManufactureTaskSessionStateEnum::CLOSED) {
            throw ValidationException::withMessages([
                'state' => __('Only a finished task session can be voided'),
            ]);
        }

        $session->update(['state' => ManufactureTaskSessionStateEnum::VOIDED]);

        CalculateJobOrderItemTaskQuantities::run($session->jobOrderItemTask);

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
        ]);
    }

    public function action(ManufactureTaskSession $session): ManufactureTaskSession
    {
        $this->asAction = true;
        $this->initialisationFromProduction($session->production, []);

        return $this->handle($session);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
