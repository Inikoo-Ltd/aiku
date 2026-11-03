<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\WorkArea;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\Production\WorkArea;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Its departments are left in no area (the foreign key nulls them) and its artisans in no area.
 */
class DeleteWorkArea extends OrgAction
{
    use WithWorkAreaAuthorisation;

    public function handle(WorkArea $workArea): void
    {
        DB::transaction(function () use ($workArea) {
            $workArea->artisans()->detach();
            $workArea->delete();
        });
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(WorkArea $workArea): void
    {
        $this->asAction = true;
        $this->initialisationFromProduction($workArea->production, []);

        $this->handle($workArea);
    }

    public function asController(Organisation $organisation, Production $production, WorkArea $workArea, ActionRequest $request): void
    {
        abort_if($workArea->production_id != $production->id, 404);
        $this->initialisationFromProduction($production, $request);

        $this->handle($workArea);
    }
}
