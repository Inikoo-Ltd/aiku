<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 10 Oct 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\Packaging;

use App\Actions\OrgAction;
use App\Models\Goods\EprManualLine;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteEprManualLine extends OrgAction
{
    public function handle(EprManualLine $eprManualLine): void
    {
        $eprManualLine->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('org-reports.'.$this->organisation->id);
    }

    public function asController(Organisation $organisation, EprManualLine $eprManualLine, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        abort_unless($eprManualLine->organisation_id === $organisation->id, 404);

        $this->handle($eprManualLine);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
