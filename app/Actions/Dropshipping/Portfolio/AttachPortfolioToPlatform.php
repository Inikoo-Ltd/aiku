<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 17:55:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\OrgAction;
use App\Models\Dropshipping\Portfolio;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class AttachPortfolioToPlatform extends OrgAction
{
    private Portfolio $portfolio;

    public function handle(Portfolio $portfolio, array $pivotData): Portfolio
    {

        $platform=$portfolio->customer->platform();

        $pivotData['group_id']        = $this->organisation->group_id;
        $pivotData['organisation_id'] = $this->organisation->id;
        $pivotData['shop_id']         = $portfolio->shop_id;
        $portfolio->platforms()->attach($platform->id, $pivotData);


        return $portfolio;
    }

    public function rules(): array
    {
        return [
            'reference' => 'nullable|string|max:255',
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->portfolio->customer->platform()==null) {
            abort(403);
        }

        if ($this->portfolio->platforms()->count() >= 1) {
            abort(403);
        }
    }

    public function action(Portfolio $portfolio, array $modelData): Portfolio
    {
        $this->portfolio = $portfolio;
        $this->initialisation($portfolio->organisation, $modelData);

        return $this->handle($portfolio, $this->validatedData);
    }

    public function asController(Organisation $organisation, Portfolio $portfolio, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        $this->handle($portfolio, $this->validatedData);
    }
}
