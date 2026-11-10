<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Nov 2026 09:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureOvertime;

use App\Actions\OrgAction;
use App\Actions\Production\Production\UI\ShowArtisansDashboard;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * TOIL hours given per overtime hour. Overtime already approved as TOIL keeps the ratio it was approved with.
 */
class SetManufactureToilRatio extends OrgAction
{
    public function handle(Production $production, array $modelData): Production
    {
        $production->update(['settings' => array_merge($production->settings ?? [], ['toil_ratio' => (float) $modelData['toil_ratio']])]);

        return $production;
    }

    public function rules(): array
    {
        return [
            'toil_ratio' => ['required', 'numeric', 'min:0.5', 'max:3'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ShowArtisansDashboard::canSeeArtisanPay($request->user(), $this->production);
    }

    public function action(Production $production, array $modelData): Production
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Organisation $organisation, Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
