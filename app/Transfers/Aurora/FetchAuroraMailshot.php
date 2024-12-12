<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
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

        //        $snapshotState = match ($this->auroraModelData->{'Email Campaign State'}) {
        //            'InProcess', 'SetRecipients', 'ComposingEmail' => SnapshotStateEnum::UNPUBLISHED,
        //            default => SnapshotStateEnum::LIVE,
        //        };


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


        $subject = $this->auroraModelData->{'Email Template Subject'};
        if ($subject == '') {
            $subject = '?';
        }


        $this->parsedData['mailshot'] = [
            'subject'    => $subject,
            'type'       => $type,
            'state'      => $state,
            'source_id'  => $this->organisation->id.':'.$this->auroraModelData->{'Email Campaign Key'},
            'created_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Creation Date'}),

            'ready_at'         => $this->parseDatetime($this->auroraModelData->{'Email Campaign Composed Date'}),
            'scheduled_at'     => $this->parseDatetime($this->auroraModelData->{'Email Campaign Scheduled Date'}),
            'start_sending_at' => $this->parseDatetime($this->auroraModelData->{'Email Campaign Start Send Date'}),
            'sent_at'          => $this->parseDatetime($this->auroraModelData->{'Email Campaign End Send Date'}),
            'stopped_at'       => $this->parseDatetime($this->auroraModelData->{'Email Campaign Stopped Date'}),

            'recipients_recipe' => [],
            'fetched_at'        => now(),
            'last_fetched_at'   => now(),
        ];
        //
        //        $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Last Edited'});
        //
        //        if (!$snapshotPublishedAt) {
        //            $snapshotPublishedAt = $this->parseDatetime($this->auroraModelData->{'Email Template Created'});
        //        }
        //        if (!$snapshotPublishedAt) {
        //            $snapshotPublishedAt = now();
        //        }

        //        $this->parsedData['email'] = [
        //            'subject'         => $this->auroraModelData->{'Email Template Subject'},
        //            'builder'         => EmailBuilderEnum::BEEFREE,
        //            'layout'          => json_decode($this->auroraModelData->{'Email Template Editing JSON'}, true),
        //            'compiled_layout' => $this->auroraModelData->{'Email Template HTML'},
        //            'state'           => $snapshotState,
        //            'published_at'    => $snapshotPublishedAt,
        //            'recyclable'      => false,
        //            'first_commit'    => true,
        //            'fetched_at'      => now(),
        //            'last_fetched_at' => now(),
        //            'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},
        //
        //            'snapshot_state'        => $snapshotState,
        //            'snapshot_published_at' => $snapshotPublishedAt,
        //            'snapshot_recyclable'   => false,
        //            'snapshot_first_commit' => true,
        //            'snapshot_source_id'    => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},
        //
        //        ];
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
