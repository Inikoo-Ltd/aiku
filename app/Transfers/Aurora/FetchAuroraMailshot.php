<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraMailshot extends FetchAurora
{
    protected function parseModel(): void
    {
        if (!in_array($this->auroraModelData->{'Email Campaign Type'}, ['Newsletter', 'Marketing', 'AbandonedCart'])) {
            return;
        }

        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Store Key'});


        //enum('InProcess','SetRecipients','ComposingEmail','Ready','Scheduled','Sending','Sent','Cancelled','Stopped')
        $state = match ($this->auroraModelData->{'Email Campaign State'}) {
            'InProcess', 'SetRecipients', 'ComposingEmail' => MailshotStateEnum::IN_PROCESS,
            'Ready' => MailshotStateEnum::READY,
            'Scheduled' => MailshotStateEnum::SCHEDULED,
            'Sending' => MailshotStateEnum::SENDING,
            'Sent' => MailshotStateEnum::SENT,
            'Cancelled' => MailshotStateEnum::CANCELLED,
            'Stopped' => MailshotStateEnum::STOPPED,
        };


        $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'});
        if ($this->auroraModelData->{'Email Campaign State'} == 'ComposingEmail' and $this->parseDatetime($this->auroraModelData->{'Email Campaign Setup Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Setup Date'});
        }
        if ($this->auroraModelData->{'Email Campaign State'} == 'Ready' && $this->parseDatetime($this->auroraModelData->{'Email Campaign Composed Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Composed Date'});
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'Scheduled' && $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'});
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'Sending' && $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'});
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'Sent' && $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'});
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'Cancelled' && $this->parseDatetime($this->auroraModelData->{'Email Campaign Last Updated Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Last Updated Date'});
        }

        if ($this->auroraModelData->{'Email Campaign State'} == 'Stopped' && $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'})) {
            $date = $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'});
        }





        if ($this->auroraModelData->{'Email Campaign Type'} == 'Newsletter') {
            $type   = MailshotTypeEnum::NEWSLETTER;
            $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::NEWSLETTER)->first();
        } elseif ($this->auroraModelData->{'Email Campaign Type'} == 'Marketing') {
            $type   = MailshotTypeEnum::MARKETING;
            $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::MARKETING)->first();
        } elseif ($this->auroraModelData->{'Email Campaign Type'} == 'Invite Full Mailshot') {
            $type   = MailshotTypeEnum::INVITE;
            $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::INVITE)->first();
        } elseif ($this->auroraModelData->{'Email Campaign Type'} == 'AbandonedCart') {
            if ($shop->type == ShopTypeEnum::DROPSHIPPING or $shop->type == ShopTypeEnum::FULFILMENT) {
                return;
            }
            $type   = MailshotTypeEnum::ABANDONED_CART;
            $outbox = $shop->outboxes()->where('code', OutboxCodeEnum::ABANDONED_CART)->first();
        } else {
            dd($this->auroraModelData->{'Email Campaign Type'});
        }


        if (!$outbox) {
            dd($this->auroraModelData);
        }


        $this->parsedData['outbox'] = $outbox;

        $this->parsedData['source_template_id'] = $this->auroraModelData->{'Email Campaign Email Template Key'};


        $subject = trim($this->auroraModelData->{'Email Template Subject'});
        if ($subject == '') {
            $subject = '?';
        }


        $this->parsedData['mailshot'] = [
            'subject'    => $subject,
            'type'       => $type,
            'state'      => $state,
            'source_id'  => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'}),
            'date'       => $date,

            'ready_at'         => $this->parseDatetime($this->auroraModelData->{'Email Campaign Composed Date'}),
            'scheduled_at'     => $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'}),
            'start_sending_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'}),
            'sent_at'          => $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'}),
            'stopped_at'       => $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'}),

            'recipients_recipe' => [],
            'fetched_at'        => now(),
            'last_fetched_at'   => now(),
        ];

        dd($this->parsedData['mailshot'], $this->auroraModelData, );
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Email Campaign Dimension')
            ->leftJoin('Email Template Dimension', 'Email Campaign Email Template Key', 'Email Template Key')
            ->leftJoin('Email Campaign Type Dimension', 'Email Campaign Email Template Type Key', 'Email Campaign Type Key')
            ->where('Email Campaign Key', $id)->first();
    }
}
