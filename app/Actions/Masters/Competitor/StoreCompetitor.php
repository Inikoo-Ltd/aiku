<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\Competitor;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMastersEditAuthorisation;
use App\Models\Masters\Competitor;
use App\Models\Masters\MasterShop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class StoreCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorRules;

    public function handle(MasterShop $masterShop, array $modelData): Competitor
    {
        /** @var Competitor $competitor */
        $competitor = $masterShop->competitors()->create($modelData + ['group_id' => $masterShop->group_id]);

        return $competitor;
    }

    public function rules(): array
    {
        return $this->competitorRules(required: true);
    }

    public function asController(MasterShop $masterShop, ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function action(MasterShop $masterShop, array $modelData): Competitor
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterShop->group, $modelData);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function htmlResponse(Competitor $competitor): RedirectResponse
    {
        return Redirect::route('grp.masters.master_shops.show', [
            'masterShop' => $competitor->masterShop->slug,
            'tab'        => 'competitors',
        ]);
    }
}
