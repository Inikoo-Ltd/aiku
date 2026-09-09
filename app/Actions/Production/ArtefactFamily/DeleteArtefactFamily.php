<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily;

use App\Actions\OrgAction;
use App\Models\Production\Artefact;
use App\Models\Production\ArtefactFamily;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteArtefactFamily extends OrgAction
{
    private ArtefactFamily $artefactFamily;

    /**
     * @throws \Throwable
     */
    public function handle(ArtefactFamily $artefactFamily): int
    {
        return DB::transaction(function () use ($artefactFamily) {
            /* The artefacts stay in the department, they just lose the family. */
            $orphaned = Artefact::where('artefact_family_id', $artefactFamily->id)
                ->update(['artefact_family_id' => null]);

            DB::table('audits')
                ->where('auditable_type', 'ArtefactFamily')
                ->where('auditable_id', $artefactFamily->id)
                ->delete();

            $artefactFamily->forceDelete();

            return $orphaned;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(["org-supervisor.{$this->organisation->id}", "productions_rd.{$this->production->id}.edit"]);
    }

    /**
     * @throws \Throwable
     */
    public function action(ArtefactFamily $artefactFamily): int
    {
        $this->asAction = true;
        $this->initialisationFromProduction($artefactFamily->production, []);

        return $this->handle($artefactFamily);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ArtefactFamily $artefactFamily, ActionRequest $request): int
    {
        $this->artefactFamily = $artefactFamily;
        $this->initialisationFromProduction($artefactFamily->production, $request);

        return $this->handle($artefactFamily);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.org.productions.show.crafts.artefact_families.index', [
            $this->artefactFamily->organisation->slug,
            $this->artefactFamily->production->slug,
        ]);
    }
}
