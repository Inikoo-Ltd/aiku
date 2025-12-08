<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Jul 2024 11:40:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraCharge extends FetchAurora
{
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Charge Store Key'});

        $trigger = match ($this->auroraModelData->{'Charge Trigger'}) {
            'Product' => ChargeTriggerEnum::PRODUCT,
            'Order' => ChargeTriggerEnum::ORDER,
            'Selected by Customer' => ChargeTriggerEnum::SELECTED_BY_CUSTOMER,
            'Payment Type' => ChargeTriggerEnum::PAYMENT_ACCOUNT,
            default => null
        };

        $type = match ($this->auroraModelData->{'Charge Scope'}) {
            'Hanging' => ChargeTypeEnum::HANGING,
            'Premium' => ChargeTypeEnum::PREMIUM,
            'Tracking' => ChargeTypeEnum::TRACKING,
            'Pastpay' => ChargeTypeEnum::PAYMENT,
            'Insurance' => ChargeTypeEnum::INSURANCE,
            'CoD' => ChargeTypeEnum::COD,
            'Packing' => ChargeTypeEnum::PACKING,
            default => null
        };

        $settings = [
            'rules' => $this->auroraModelData->{'Charge Terms Metadata'},
            'rule_subject' => $this->auroraModelData->{'Charge Terms Type'},
        ];

        if ($this->auroraModelData->{'Charge Scope'} != 'Pastpay') {
            $settings['amount'] = $this->auroraModelData->{'Charge Metadata'};
        }

        $description = $this->clearTextWithHtml($this->auroraModelData->{'Charge Public Description'});

        if ($description == '') {
            $description = $this->clearTextWithHtml($this->auroraModelData->{'Charge Description'});
        }

        $name = $this->auroraModelData->{'Charge Name'};
        $code = $this->auroraModelData->{'Charge Scope'}.'-'.$shop->code;
        if ($this->auroraModelData->{'Charge Name'} == '30PastPay') {
            $code = $this->auroraModelData->{'Charge Scope'}.'-30-'.$shop->code;
            $name = 'Pastpay charge (30 days)';
        } elseif ($this->auroraModelData->{'Charge Name'} == '60PastPay') {
            $code = $this->auroraModelData->{'Charge Scope'}.'-60-'.$shop->code;
            $name = 'Pastpay charge (60 days)';
        }

        $state = $this->auroraModelData->{'Charge Active'} === 'Yes' ? ChargeStateEnum::ACTIVE : ChargeStateEnum::DISCONTINUED;
        $this->parsedData['shop'] = $shop;
        $this->parsedData['charge'] = [
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'type' => $type,
            'trigger' => $trigger,
            'settings' => $settings,
            'state' => $state,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Charge Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];

        $createdBy = $this->auroraModelData->{'Charge Begin Date'};

        if ($createdBy) {
            $this->parsedData['charge']['created_by'] = $createdBy;
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Charge Dimension')
            ->where('Charge Key', $id)->first();
    }
}
