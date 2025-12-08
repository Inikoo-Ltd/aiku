<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\EmailTrackingEvent\EmailTrackingEventTypeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmailTrackingEvent extends FetchAurora
{
    protected function parseModel(): void
    {
        if (! $this->auroraModelData->{'Email Tracking Event Type'}) {
            return;
        }
        $dispatchedEmail = $this->parseDispatchedEmail($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Event Tracking Key'});

        if (! $dispatchedEmail) {
            return;
        }

        $type = match ($this->auroraModelData->{'Email Tracking Event Type'}) {
            'Sent','Send' => EmailTrackingEventTypeEnum::SENT,
            'Rejected by SES' => EmailTrackingEventTypeEnum::DECLINED_BY_PROVIDER,
            'Hard Bounce' => EmailTrackingEventTypeEnum::HARD_BOUNCE,
            'Soft Bounce' => EmailTrackingEventTypeEnum::SOFT_BOUNCE,
            'Spam' => EmailTrackingEventTypeEnum::MARKED_AS_SPAM,
            'Delivered' => EmailTrackingEventTypeEnum::DELIVERED,
            'Opened','Read' => EmailTrackingEventTypeEnum::OPENED,
            'Clicked' => EmailTrackingEventTypeEnum::CLICKED,
            'Send to SES Error','Error' => EmailTrackingEventTypeEnum::ERROR,
            default => null
        };

        if ($type == null) {
            print_r($this->auroraModelData);

            return;
        }
        $this->parsedData['dispatchedEmail'] = $dispatchedEmail;

        $data = [];

        if ($this->auroraModelData->{'Email Tracking Event Data'} and
            ! (
                $this->auroraModelData->{'Email Tracking Event Data'} != '""' or
                $this->auroraModelData->{'Email Tracking Event Data'} != '{}'
            )
        ) {
            $data['sns'] = json_decode($this->auroraModelData->{'Email Tracking Event Data'});
        }
        if ($this->auroraModelData->{'Email Tracking Event Note'} != '') {
            $data['source_original_note'] = $this->auroraModelData->{'Email Tracking Event Note'};
        }

        if ($this->auroraModelData->{'Email Tracking Event Status Code'} != '') {
            $data['status_code'] = $this->auroraModelData->{'Email Tracking Event Status Code'};
        }

        $this->parsedData['emailTrackingEvent'] = [
            'type' => $type,
            // 'provider_reference' => $this->auroraModelData->{'Email Tracking Event Message ID'},
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Event Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Tracking Event Date'}),
            'data' => $data,
            'fetched_at' => now(),
            'last_fetched_at' => now(),
        ];
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Tracking Event Dimension')
            ->where('Email Tracking Event Key', $id)->first();
    }
}
