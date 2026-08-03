<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 Jul 2026 07:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class SetClockingMachineKioskToken extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachine $clockingMachine, bool $revoke = false): ClockingMachine
    {
        $clockingMachine->update([
            'kiosk_token' => $revoke ? null : Str::random(48),
        ]);

        return $clockingMachine->refresh();
    }

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): JsonResponse
    {
        $this->initialisation($clockingMachine->organisation, $request);

        $clockingMachine = $this->handle(
            $clockingMachine,
            (bool)$this->validatedData['revoke']
        );

        return response()->json([
            'success'     => true,
            'kiosk_token' => $clockingMachine->kiosk_token,
            'kiosk_url'   => $clockingMachine->kiosk_token
                ? route('grp.kiosk.show', ['kioskToken' => $clockingMachine->kiosk_token])
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'revoke' => ['sometimes', 'boolean'],
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('revoke')) {
            $this->set('revoke', false);
        }
    }
}
