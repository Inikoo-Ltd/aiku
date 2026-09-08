<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 03 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithAccountingEditAuthorisation;
use App\Models\Goods\TradeUnit;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteTradeUnitTariffCodeOverride extends OrgAction
{
    use WithAccountingEditAuthorisation;

    public function handle(TradeUnit $tradeUnit, Organisation $organisation): void
    {
        $tradeUnit->tariffCodeOverrides()->where('organisation_id', $organisation->id)->delete();
        SetTradeUnitTariffCodeOverride::make()->hydrateProductsTariffCode($tradeUnit, $organisation);
    }

    public function asController(TradeUnit $tradeUnit, Organisation $organisation, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);
        $this->handle($tradeUnit, $organisation);
    }

    public function action(TradeUnit $tradeUnit, Organisation $organisation): void
    {
        $this->asAction = true;
        $this->initialisation($organisation, []);
        $this->handle($tradeUnit, $organisation);
    }
}
