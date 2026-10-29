<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Enums\Production\JobOrder\JobOrderStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * An experiment the development team stops before launch. It stays in the Development room with its recipe
 * and trial runs as the record of what was tried, and can never be launched.
 */
class CancelArtefactDevelopment extends OrgAction
{
    private Artefact $artefact;

    public function handle(Artefact $artefact, ?User $user, array $modelData): Artefact
    {
        $artefact->update([
            'state' => ArtefactStateEnum::CANCELLED,
            'data'  => array_merge($artefact->data ?? [], [
                'cancellation' => [
                    'reason'  => trim($modelData['reason']),
                    'user_id' => $user?->id,
                    'name'    => $user ? ($user->contact_name ?: $user->username) : null,
                    'at'      => now()->toIso8601String(),
                ],
            ]),
        ]);

        SetArtefactState::hydrateParents($artefact);

        return $artefact;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artefact->production_id !== $this->production->id) {
            $validator->errors()->add('reason', __('This artefact is not made in this factory'));

            return;
        }

        if ($this->artefact->state !== ArtefactStateEnum::IN_PROCESS) {
            $validator->errors()->add('reason', __(':code is not an experiment in development', ['code' => $this->artefact->code]));

            return;
        }

        $openTrialRuns = $this->artefact->jobOrderItems()
            ->whereHas('jobOrder', fn ($query) => $query->whereIn('state', JobOrderStateEnum::open()))
            ->with('jobOrder:id,reference')
            ->get()
            ->pluck('jobOrder.reference')
            ->unique();

        if ($openTrialRuns->isNotEmpty()) {
            $validator->errors()->add('reason', __('Receive or cancel its open trial runs first: :references', ['references' => $openTrialRuns->implode(', ')]));
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            'org-supervisor.'.$this->organisation->id,
            "productions_rd.{$this->production->id}.edit",
        ]);
    }

    public function action(Artefact $artefact, array $modelData, ?User $user = null): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, $modelData);

        return $this->handle($artefact, $user, $this->validatedData);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $request->user(), $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
