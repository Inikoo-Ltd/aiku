<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact;

use App\Actions\OrgAction;
use App\Actions\Production\ArtefactDepartment\Hydrators\ArtefactDepartmentHydrateArtefacts;
use App\Actions\Production\ArtefactFamily\Hydrators\ArtefactFamilyHydrateArtefacts;
use App\Enums\Production\Artefact\ArtefactStateEnum;
use App\Http\Resources\Production\ArtefactResource;
use App\Models\Production\Artefact;
use App\Models\Production\Production;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetArtefactState extends OrgAction
{
    public function handle(Artefact $artefact, array $modelData): Artefact
    {
        $state = ArtefactStateEnum::from($modelData['state'] instanceof ArtefactStateEnum ? $modelData['state']->value : $modelData['state']);

        if ($refusal = self::crossesLaunch($artefact, $state)) {
            throw ValidationException::withMessages(['state' => $refusal]);
        }

        $artefact->update(['state' => $state]);
        self::hydrateParents($artefact);

        return $artefact;
    }

    /**
     * An experiment becomes a product only by being launched, and a launched product never goes back to
     * being an experiment: a plain change of state may not cross that line either way. Cancelling has its
     * own action, which asks why, and a cancelled experiment is never brought back.
     */
    public static function crossesLaunch(Artefact $artefact, ArtefactStateEnum $state): ?string
    {
        if ($artefact->state === $state) {
            return null;
        }

        if ($artefact->state === ArtefactStateEnum::MERGED || $state === ArtefactStateEnum::MERGED) {
            return $artefact->state === ArtefactStateEnum::MERGED
                ? __(':code was merged back into the artefact it was branched from and stays merged', ['code' => $artefact->code])
                : __(':code is merged back from the Development room', ['code' => $artefact->code]);
        }

        if ($artefact->state === ArtefactStateEnum::CANCELLED || $state === ArtefactStateEnum::CANCELLED) {
            return $artefact->state === ArtefactStateEnum::CANCELLED
                ? __(':code was cancelled in development and stays cancelled', ['code' => $artefact->code])
                : __(':code is cancelled from the Development room, with the reason', ['code' => $artefact->code]);
        }

        if ($artefact->state->isBeforeLaunch() === $state->isBeforeLaunch()) {
            return null;
        }

        return $artefact->state->isBeforeLaunch()
            ? __(':code is still in development: launch it from the Development room', ['code' => $artefact->code])
            : __(':code is launched and can not go back to development', ['code' => $artefact->code]);
    }

    public static function hydrateParents(Artefact $artefact): void
    {
        if ($artefact->artefactFamily) {
            ArtefactFamilyHydrateArtefacts::run($artefact->artefactFamily);
        }

        if ($artefact->artefactDepartment) {
            ArtefactDepartmentHydrateArtefacts::run($artefact->artefactDepartment);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    public function rules(): array
    {
        return [
            'state' => ['required', new Enum(ArtefactStateEnum::class)],
        ];
    }

    public function action(Artefact $artefact, ArtefactStateEnum $state): Artefact
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefact->production, ['state' => $state->value]);

        return $this->handle($artefact, $this->validatedData);
    }

    public function asController(Production $production, Artefact $artefact, ActionRequest $request): Artefact
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($artefact, $this->validatedData);
    }

    public function jsonResponse(Artefact $artefact): ArtefactResource
    {
        return new ArtefactResource($artefact);
    }
}
