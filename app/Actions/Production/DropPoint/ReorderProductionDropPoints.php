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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class ReorderProductionDropPoints extends OrgAction
{
    use WithDropPointAuthorisation;

    /**
     * @param array<int, int> $dropPointIds in the order the factory wants them listed
     */
    public function handle(Production $production, array $dropPointIds): void
    {
        DB::transaction(function () use ($production, $dropPointIds) {
            foreach (array_values($dropPointIds) as $position => $dropPointId) {
                ProductionDropPoint::where('production_id', $production->id)->where('id', $dropPointId)->update(['position' => $position]);
            }
        });
    }

    public function rules(): array
    {
        return [
            'drop_point_ids'   => ['required', 'array', 'max:200'],
            'drop_point_ids.*' => ['integer', Rule::exists('production_drop_points', 'id')->where('production_id', $this->production->id)],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function asController(Production $production, ActionRequest $request): void
    {
        $this->initialisationFromProduction($production, $request);

        $this->handle($production, $this->validatedData['drop_point_ids']);
    }
}
