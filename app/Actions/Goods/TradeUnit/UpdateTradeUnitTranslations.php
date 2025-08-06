<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 11:42:45 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTradeUnitTranslations extends OrgAction
{
    use asAction;

    public function handle(TradeUnit $tradeUnit, array $modelData)
    {

    }

    public function rules(): array
    {
        return [

        ];
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request)
    {
        $this->initialisationFromGroup(group(), $request);
        $this->handle($tradeUnit, $this->validatedData);
    }

}
