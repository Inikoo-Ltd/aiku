<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 26 Nov 2026 21:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionBandOverrideReasonEnum;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionStateEnum;
use App\Models\Production\ManufacturePayBand;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class OverrideManufactureTaskSessionBand extends OrgAction
{
    use WithManufacturePayrollLock;

    /**
     * The production manager pays a finished session at another band than its speed earned (HELP-4967), e.g. work that has
     * to be redone through the artisan's fault. The override is kept on the session, so every later recalculation keeps it;
     * a null band gives the session back the band its speed earns.
     */
    public function handle(ManufactureTaskSession $session, array $modelData): ManufactureTaskSession
    {
        return DB::transaction(function () use ($session, $modelData) {
            $session  = ManufactureTaskSession::lockForUpdate()->find($session->id);
            $bandCode = $modelData['band_code'] ?? null;

            if ($this->payrollEditor && !$this->payrollEditor->authTo('org-supervisor.'.$session->organisation_id)) {
                $this->refuse('band_code', __('Only the production manager can change the band of a session'));
            }
            if ($bandCode !== null && !self::canBeOverridden($session)) {
                $this->refuse('band_code', __('Only a finished production session paid by speed can have its band changed'));
            }
            if ($this->payrollEditor && $this->payrollEditor->id == $session->user_id) {
                $this->refuse('band_code', __('You cannot change the band of your own session'));
            }
            if ($bandCode !== null && !ManufacturePayBand::effectiveAt($session->production_id, $session->ended_at)->where('code', $bandCode)->where(fn ($query) => $query->whereNotNull('target_multiplier')->orWhere('code', '0'))->exists()) {
                $this->refuse('band_code', __('Band :band cannot be chosen for this session', ['band' => $bandCode]));
            }

            return $this->changeInPayWeek($session->production, $session, $session->user_id, 'band', [$session->started_at, $session->ended_at], ['band_code', 'pay'], $modelData['lock_reason'] ?? null, function () use ($session, $bandCode, $modelData) {
                $session->update($bandCode === null ? [
                    'band_override_code'   => null,
                    'band_override_reason' => null,
                    'band_override_note'   => null,
                    'band_overridden_by'   => null,
                    'band_overridden_at'   => null,
                ] : [
                    'band_override_code'   => $bandCode,
                    'band_override_reason' => $modelData['reason'],
                    'band_override_note'   => $modelData['note'] ?? null,
                    'band_overridden_by'   => $this->payrollEditor?->id,
                    'band_overridden_at'   => now(),
                ]);

                return CalculateManufactureTaskSessionPay::run($session);
            });
        });
    }

    public static function canBeOverridden(ManufactureTaskSession $session): bool
    {
        return $session->state == ManufactureTaskSessionStateEnum::CLOSED
            && $session->isPaidBySpeedBand()
            && $session->band_code !== null;
    }

    private function refuse(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }

    public function rules(): array
    {
        return [
            'band_code'   => ['present', 'nullable', 'string', 'max:8'],
            'reason'      => ['required_with:band_code', 'nullable', Rule::enum(ManufactureTaskSessionBandOverrideReasonEnum::class)],
            'note'        => ['nullable', 'string', 'max:1000', 'required_if:reason,'.ManufactureTaskSessionBandOverrideReasonEnum::OTHER->value],
            'lock_reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('org-supervisor.'.$this->organisation->id);
    }

    public function action(ManufactureTaskSession $session, array $modelData, ?User $editor = null): ManufactureTaskSession
    {
        $this->asAction      = true;
        $this->payrollEditor = $editor;
        $this->initialisationFromProduction($session->production, $modelData);

        return $this->handle($session, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->payrollEditor = $request->user();
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
