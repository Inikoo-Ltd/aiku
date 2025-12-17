<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Jun 2024 00:11:33 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\TopUp;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateTopUps;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTopUps;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateTopUps;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateTopUps;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\TopUp\TopUpStatusEnum;
use App\Models\Accounting\TopUp;

class SetTopUpStatusToSuccess extends OrgAction
{
    use WithActionUpdate;

    public function handle(TopUp $topUp): TopUp
    {
        $modelData['status'] = TopUpStatusEnum::SUCCESS;
        $this->update($topUp, $modelData);

        data_forget($modelData, 'status');
        data_set($modelData, 'amount', $topUp->amount);
        data_set($modelData, 'top_up_id', $topUp->id);
        data_set($modelData, 'type', CreditTransactionTypeEnum::TOP_UP);
        StoreCreditTransaction::make()->action($topUp->customer, $modelData);
        $topUp->refresh();

        CustomerHydrateTopUps::dispatch($topUp->customer_id);
        ShopHydrateTopUps::dispatch($topUp->shop);
        OrganisationHydrateTopUps::dispatch($topUp->organisation);
        GroupHydrateTopUps::dispatch($topUp->group);

        return $topUp;
    }

    public function action(TopUp $topUp): TopUp
    {
        $this->asAction = true;
        $this->initialisation($topUp->organisation, []);
        return $this->handle($topUp);
    }
}
