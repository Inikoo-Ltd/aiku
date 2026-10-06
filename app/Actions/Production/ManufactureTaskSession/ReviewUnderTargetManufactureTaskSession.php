<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Oct 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTaskSession;

use App\Actions\OrgAction;
use App\Enums\Production\ManufactureTaskSession\ManufactureTaskSessionUnderTargetReasonEnum;
use App\Models\Production\ManufactureTaskSession;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class ReviewUnderTargetManufactureTaskSession extends OrgAction
{
    public function handle(ManufactureTaskSession $session, User $reviewer, array $modelData): ManufactureTaskSession
    {
        if (!$session->is_under_target) {
            throw ValidationException::withMessages([
                'under_target_reason' => __('This entry is not under target'),
            ]);
        }

        $session->update([
            'under_target_reason'      => $modelData['under_target_reason'],
            'under_target_note'        => $modelData['under_target_note'] ?? null,
            'under_target_reviewed_by' => $reviewer->id,
            'under_target_reviewed_at' => now(),
        ]);

        return $session;
    }

    public function rules(): array
    {
        return [
            'under_target_reason' => ['required', Rule::enum(ManufactureTaskSessionUnderTargetReasonEnum::class)],
            'under_target_note'   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
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

    public function action(ManufactureTaskSession $session, User $reviewer, array $modelData): ManufactureTaskSession
    {
        $this->asAction = true;
        $this->initialisationFromProduction($session->production, $modelData);

        return $this->handle($session, $reviewer, $this->validatedData);
    }

    public function asController(ManufactureTaskSession $manufactureTaskSession, ActionRequest $request): ManufactureTaskSession
    {
        $this->initialisationFromProduction($manufactureTaskSession->production, $request);

        return $this->handle($manufactureTaskSession, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
