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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCompetitor extends OrgAction
{
    use WithMastersEditAuthorisation;
    use WithCompetitorRules;

    public function handle(MasterShop $masterShop, array $modelData): Competitor
    {
        /** @var Competitor $competitor */
        $competitor = $masterShop->competitors()->create(Arr::except($modelData, 'master_shop_id') + ['group_id' => $masterShop->group_id]);

        if (!$competitor->prices_by_hand) {
            FetchCompetitorIntel::dispatch($competitor);
        }

        return $competitor;
    }

    public function rules(): array
    {
        return $this->competitorRules(required: true) + [
            'master_shop_id' => ['required', Rule::exists('master_shops', 'id')->where('group_id', $this->group->id)],
        ];
    }

    public function asController(ActionRequest $request): Competitor
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle(MasterShop::find($this->validatedData['master_shop_id']), $this->validatedData);
    }

    public function action(MasterShop $masterShop, array $modelData): Competitor
    {
        $this->asAction = true;
        $this->initialisationFromGroup($masterShop->group, $modelData + ['master_shop_id' => $masterShop->id]);

        return $this->handle($masterShop, $this->validatedData);
    }

    public function htmlResponse(Competitor $competitor): RedirectResponse
    {
        return Redirect::route('grp.masters.competitors.show', ['competitor' => $competitor->slug]);
    }
}
