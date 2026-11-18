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

class UpdateProductionDropPoint extends OrgAction
{
    use WithDropPointAuthorisation;

    private ProductionDropPoint $dropPoint;

    public function handle(ProductionDropPoint $dropPoint, array $modelData): ProductionDropPoint
    {
        $dropPoint->update($modelData);

        return $dropPoint;
    }

    public function rules(): array
    {
        return [
            'name'     => [
                'sometimes', 'required', 'string', 'max:64',
                Rule::unique('production_drop_points', 'name')
                    ->where('production_id', $this->production->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->dropPoint->id),
            ],
            'position' => ['sometimes', 'required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }

    public function action(ProductionDropPoint $dropPoint, array $modelData): ProductionDropPoint
    {
        $this->asAction  = true;
        $this->dropPoint = $dropPoint;
        $this->initialisationFromProduction($dropPoint->production, $modelData);

        return $this->handle($dropPoint, $this->validatedData);
    }

    public function asController(Production $production, ProductionDropPoint $productionDropPoint, ActionRequest $request): ProductionDropPoint
    {
        abort_if($productionDropPoint->production_id !== $production->id, 404);
        $this->dropPoint = $productionDropPoint;
        $this->initialisationFromProduction($production, $request);

        return $this->handle($productionDropPoint, $this->validatedData);
    }
}
