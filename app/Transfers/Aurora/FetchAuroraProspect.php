<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\CRM\Prospect\ProspectContactedStateEnum;
use App\Enums\CRM\Prospect\ProspectFailStatusEnum;
use App\Enums\CRM\Prospect\ProspectStateEnum;
use App\Enums\CRM\Prospect\ProspectSuccessStatusEnum;
use Exception;
use Illuminate\Support\Facades\DB;

class FetchAuroraProspect extends FetchAurora
{
    /**
     * @throws \Exception
     */
    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Prospect Store Key'});

        if ($shop->is_aiku) {
            return;
        }

        $customerId = null;
        if ($this->auroraModelData->{'Prospect Customer Key'}) {
            $customer = $this->parseCustomer(
                $this->organisation->id.':'.
                $this->auroraModelData->{'Prospect Customer Key'}
            );
            $customerId = $customer?->id;
        }
        $lastContacted = null;
        if ($this->parseDatetime($this->auroraModelData->{'Prospect Last Contacted Date'})) {
            $lastContacted = $this->parseDatetime($this->auroraModelData->{'Prospect Last Contacted Date'});
        }

        $dontContactMe = false;
        $contactedState = ProspectContactedStateEnum::NA;
        $failStatus = ProspectFailStatusEnum::NA;
        $successStatus = ProspectSuccessStatusEnum::NA;
        switch ($this->auroraModelData->{'Prospect Status'}) {
            case 'NoContacted':
                $state = ProspectStateEnum::NO_CONTACTED;
                $lastContacted = null;
                break;
            case 'Contacted':
                $state = ProspectStateEnum::CONTACTED;
                $contactedState = ProspectContactedStateEnum::NEVER_OPEN;
                break;
            case 'NotInterested':
                $state = ProspectStateEnum::FAIL;
                $failStatus = ProspectFailStatusEnum::UNSUBSCRIBED;
                $dontContactMe = true;
                break;
            case 'Registered':
                $state = ProspectStateEnum::SUCCESS;
                $successStatus = ProspectSuccessStatusEnum::REGISTERED;
                break;
            case 'Invoiced':
                $state = ProspectStateEnum::SUCCESS;
                $successStatus = ProspectSuccessStatusEnum::INVOICED;
                break;
            case 'Bounced':
                $state = ProspectStateEnum::FAIL;
                $failStatus = ProspectFailStatusEnum::HARD_BOUNCED;
                break;
            default:
                throw new Exception('Invalid status: '.$this->auroraModelData->{'Prospect Status'});
        }

        $email = $this->auroraModelData->{'Prospect Main Plain Email'};
        $email = preg_replace('/\.+/', '.', $email);

        $phone = $this->auroraModelData->{'Prospect Main Plain Mobile'};
        if (strlen($phone) <= 5 or strlen($phone) > 24) {
            $phone = null;
        }

        $this->parsedData['prospect'] =
            [
                'state' => $state,
                'contacted_state' => $contactedState,
                'fail_status' => $failStatus,
                'success_status' => $successStatus,
                'dont_contact_me' => $dontContactMe,
                'last_contacted_at' => $lastContacted,
                'contact_name' => $this->auroraModelData->{'Prospect Main Contact Name'},
                'company_name' => $this->auroraModelData->{'Prospect Company Name'},
                'email' => $email,
                'phone' => $phone,
                'contact_website' => $this->auroraModelData->{'Prospect Website'},
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Prospect Key'},
                'customer_id' => $customerId,
                'address' => $this->parseAddress(prefix: 'Prospect', auAddressData: $this->auroraModelData),
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];
        if ($this->parseDatetime($this->auroraModelData->{'Prospect Created Date'})) {
            $this->parsedData['prospect']['created_at'] = $this->auroraModelData->{'Prospect Created Date'};
        }

        $this->parsedData['shop'] = $shop;
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Prospect Dimension')
            ->where('Prospect Key', $id)->first();
    }
}
