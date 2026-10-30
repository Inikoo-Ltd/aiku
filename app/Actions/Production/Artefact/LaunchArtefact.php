<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The end of an experiment: the artefact becomes a product the factory makes for sale. From here it is in
 * the artefacts list, on the boards and in normal job orders, and its hours are paid as production again.
 */
class LaunchArtefact extends OrgAction
{
    private Artefact $artefact;

    public function handle(Artefact $artefact, ?User $user): Artefact
    {
        $artefact->update([
            'state'             => ArtefactStateEnum::ACTIVE,
            'is_in_development' => false,
            'data'              => array_merge($artefact->data ?? [], [
                'launch' => [
                    'user_id' => $user?->id,
                    'name'    => $user ? ($user->contact_name ?: $user->username) : null,
                    'at'      => now()->toIso8601String(),
                ],
            ]),
        ]);

        SetArtefactState::hydrateParents($artefact);

        return $artefact;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->artefact->production_id !== $this->production->id) {
            $validator->errors()->add('launch', __('This artefact is not made in this factory'));

            return;
        }

        if ($this->artefact->state !== ArtefactStateEnum::IN_PROCESS) {
            $validator->errors()->add('launch', __(':code is not an experiment in development', ['code' => $this->artefact->code]));

            return;
        }

        $original = $this->artefact->branchedFrom;
        if ($original && $this->artefact->org_stock_id && $this->artefact->org_stock_id === $original->org_stock_id) {
            $validator->errors()->add('launch', __(':code is stocked as the SKO of :original, the artefact it was branched from: give it its own SKO to launch it as its own product, or merge it back', [
                'code'     => $this->artefact->code,
                'original' => $original->code,
            ]));

            return;
        }

        $missing = collect(GetArtefactLaunchReadiness::run($this->artefact)['checks'])
            ->filter(fn (array $check) => $check['required'] && !$check['done'])
            ->pluck('label');

        if ($missing->isNotEmpty()) {
            $validator->errors()->add('launch', __('Not ready to launch, still missing: :missing', ['missing' => $missing->implode('; ')]));
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

    public function action(Artefact $artefact, ?User $user = null): Artefact
    {
        $this->asAction = true;
        $this->artefact = $artefact;
        $this->initialisationFromProduction($artefact->production, []);

        return $this->handle($artefact, $user);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->artefact = $artefact;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
