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
use App\Rules\IUnique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreWorkArea extends OrgAction
{
    use WithWorkAreaAuthorisation;

    public function handle(Production $production, array $modelData): WorkArea
    {
        /** @var WorkArea $workArea */
        $workArea = $production->workAreas()->create([
            'group_id'        => $production->group_id,
            'organisation_id' => $production->organisation_id,
            'name'            => $modelData['name'],
        ]);

        return $workArea;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', new IUnique(table: 'work_areas', extraConditions: [['column' => 'production_id', 'value' => $this->production->id]])],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(Production $production, array $modelData): WorkArea
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): WorkArea
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
