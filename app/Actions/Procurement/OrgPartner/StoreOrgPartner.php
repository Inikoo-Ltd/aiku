<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 14:45:14 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\OrgPartner;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Search\OrgPartnerRecordSearch;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgPartner extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Organisation $organisation, Organisation $partner, $modelData = []): OrgPartner
    {
        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'partner_id', $partner->id);
        data_set($modelData, 'status', $partner->status, false);

        $orgPartner = DB::transaction(function () use ($organisation, $modelData) {
            /** @var OrgPartner $orgPartner */
            $orgPartner = $organisation->orgPartners()->create($modelData);
            $orgPartner->stats()->create();

            return $orgPartner;
        });

        OrgPartnerRecordSearch::dispatch($orgPartner);

        return $orgPartner;
    }

    public function rules(ActionRequest $request): array
    {
        return [
        ];
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation $organisation, Organisation $partner, $modelData = [], $hydratorsDelay = 0): OrgPartner
    {
        $this->asAction = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($organisation, $partner, $this->validatedData);
    }
}
