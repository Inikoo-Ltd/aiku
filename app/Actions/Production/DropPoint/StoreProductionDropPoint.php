<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\DropPoint;

use App\Actions\OrgAction;
use App\Models\Production\Production;
use App\Models\Production\ProductionDropPoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProductionDropPoint extends OrgAction
{
    use WithDropPointAuthorisation;

    public function handle(Production $production, array $modelData): ProductionDropPoint
    {
        /** @var ProductionDropPoint $dropPoint */
        $dropPoint = $production->dropPoints()->create([
            'group_id'        => $production->group_id,
            'organisation_id' => $production->organisation_id,
            'name'            => $modelData['name'],
            'position'        => ((int) ProductionDropPoint::where('production_id', $production->id)->max('position')) + 1,
        ]);

        return $dropPoint;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:64',
                Rule::unique('production_drop_points', 'name')->where('production_id', $this->production->id)->whereNull('deleted_at'),
            ],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(Production $production, array $modelData): ProductionDropPoint
    {
        $this->asAction = true;
        $this->initialisationFromProduction($production, $modelData);

        return $this->handle($production, $this->validatedData);
    }

    public function asController(Production $production, ActionRequest $request): ProductionDropPoint
    {
        $this->initialisationFromProduction($production, $request);

        return $this->handle($production, $this->validatedData);
    }
}
