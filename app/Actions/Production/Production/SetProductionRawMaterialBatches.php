<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * The Production Manager switches on, for one production, the tablet asking artisans for the batch code and
 * expiry date of each raw material when they finish a step (HELP-4699). Other productions are not affected.
 */
class SetProductionRawMaterialBatches extends OrgAction
{
    use WithActionUpdate;

    /**
     * @param  array{enabled: bool}  $modelData
     */
    public function handle(Production $production, array $modelData): Production
    {
        return $this->update($production, ['settings' => ['records_raw_material_batches' => (bool) $modelData['enabled']]], ['settings']);
    }

    public static function canSet(User $user, Production $production): bool
    {
        return $user->authTo('org-supervisor.'.$production->organisation_id);
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canSet($request->user(), $this->production);
    }

    public function action(Production $production, array $modelData): Production
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): Production
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
