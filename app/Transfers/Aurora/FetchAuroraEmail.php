<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\Comms\Email\EmailBuilderEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use Illuminate\Support\Facades\DB;

class FetchAuroraEmail extends FetchAurora
{
    protected function parseModel(): void
    {
        $parent = null;

        $snapshotState = null;
        $snapshotRecyclable = false;
        $publishedAt = null;
        $firstCommit = false;

        $builder = EmailBuilderEnum::BEEFREE;

        $outbox = $this->parseOutbox($this->organisation->id.':'.$this->auroraModelData->{'Email Template Email Campaign Type Key'});

        if ($this->auroraModelData->{'Email Template Scope'} == 'EmailCampaign' or $this->auroraModelData->{'Email Template Scope'} == 'Mailshot') {
            $emailCampaignData = DB::connection('aurora')->table('Email Campaign Dimension')->where('Email Campaign Key', $this->auroraModelData->{'Email Template Scope Key'})->first();
            if (! $emailCampaignData) {
                return;
            }
            if (in_array($emailCampaignData->{'Email Campaign Type'}, ['Newsletter', 'Marketing', 'Invite Full Mailshot', 'AbandonedCart'])) {
                $mailshot = $this->parseMailshot($this->organisation->id.':'.$emailCampaignData->{'Email Campaign Key'});
                if (! $mailshot) {
                    dd($emailCampaignData);
                }

                $snapshotState = match ($mailshot->state) {
                    MailshotStateEnum::IN_PROCESS => SnapshotStateEnum::UNPUBLISHED,
                    default => SnapshotStateEnum::LIVE
                };

                $firstCommit = $snapshotState == SnapshotStateEnum::LIVE;

                if ($mailshot->state == MailshotStateEnum::SENT) {
                    $snapshotRecyclable = true;
                }
                $publishedAt = $mailshot->ready_at;

                $outbox = $mailshot->outbox;
                $parent = $mailshot;
            } else {
                $emailRun = $this->parseEmailRun($this->organisation->id.':'.$emailCampaignData->{'Email Campaign Key'});
                if (! $emailRun) {
                    dd($emailCampaignData);
                }

                $outbox = $emailRun->outbox;
                $parent = $emailRun;
            }
        } elseif ($this->auroraModelData->{'Email Template Scope'} == 'EmailCampaignType') {
            return;
        }

        if (! $outbox) {
            dd($this->auroraModelData);
        }

        $subject = trim($this->auroraModelData->{'Email Template Subject'});
        $subject = preg_replace('/\s+/', ' ', $subject);

        if ($subject == '') {
            $subject = '⚠️ No subject 😕';
        }

        if (! $parent) {
            dd($this->auroraModelData);
        }

        $this->parsedData['outbox'] = $outbox;
        $this->parsedData['parent'] = $parent;
        $this->parsedData['email'] = [

            'subject' => $subject,
            'builder' => $builder,
            'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},
            'fetched_at' => now(),
            'last_fetched_at' => now(),
            'layout' => json_decode($this->auroraModelData->{'Email Template Editing JSON'}, true),
            'snapshot_state' => $snapshotState,
            'snapshot_recyclable' => $snapshotRecyclable,
            'snapshot_first_commit' => $firstCommit,
            'snapshot_source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Email Template Key'},

        ];

        if ($publishedAt) {
            $this->parsedData['email']['snapshot_published_at'] = $publishedAt;
        }

        if ($this->auroraModelData->{'Email Template HTML'} and $this->auroraModelData->{'Email Template HTML'} != 0) {
            $this->parsedData['email']['compiled_layout'] = $this->auroraModelData->{'Email Template HTML'};
        }
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Email Template Dimension')
            ->where('Email Template Key', $id)->first();
    }
}
