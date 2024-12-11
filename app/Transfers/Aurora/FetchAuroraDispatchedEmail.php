<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:09 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Comms\Mailshot\StoreMailshot;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailProviderEnum;
use App\Enums\Comms\DispatchedEmail\DispatchedEmailStateEnum;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxTypeEnum;
use App\Models\Comms\Mailshot;
use App\Models\Comms\Outbox;
use App\Models\CRM\Prospect;
use Illuminate\Support\Facades\DB;
use Str;

class FetchAuroraDispatchedEmail extends FetchAurora
{
    /**
     * @throws \Throwable
     */
    protected function parseModel(): void
    {
        if (!$this->auroraModelData->{'Email Tracking Recipient Key'}) {
            return;
        }

        if (!$this->auroraModelData->{'Email Tracking Email'}) {
            return;
        }


        //enum('Ready','Sent to SES','Rejected by SES','Sent','Soft Bounce','Hard Bounce','Delivered','Spam','Opened','Clicked','Error')
        $state = match ($this->auroraModelData->{'Email Tracking State'}) {
            'Sent to SES' => DispatchedEmailStateEnum::SENT_TO_PROVIDER,
            'Rejected by SES' => DispatchedEmailStateEnum::REJECTED_BY_PROVIDER,
            'Spam' => DispatchedEmailStateEnum::SPAM,
            default => Str::kebab($this->auroraModelData->{'Email Tracking State'})
        };


        $recipient = match ($this->auroraModelData->{'Email Tracking Recipient'}) {
            'Customer' => $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Recipient Key'}),
            'Prospect' => $this->parseProspect($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Recipient Key'}),
            'User' => $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Recipient Key'}),

            default => null
        };

        if (!$recipient) {
            return;
            // print_r($this->auroraModelData);
        }

        $parent = null;


        if ($recipient instanceof Prospect) {
            $parent = $this->parseProspectMailshot($recipient);
        } else {
            if ($this->auroraModelData->{'Email Tracking Email Mailshot Key'}) {
                $parent = $this->parseMailshot($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Email Mailshot Key'});

                if (!$parent) {
                    $parent = $this->parseEmailBulkRun($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Email Mailshot Key'});
                }
            }
            if (!$parent and $this->auroraModelData->{'Email Tracking Email Template Type Key'}) {
                $parent = $this->parseEmailOngoingRun($this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Email Template Type Key'});
            }
        }


        if (!$parent) {
            return;
          //  dd($this->auroraModelData);
        }


        $this->parsedData['recipient'] = $recipient;
        $this->parsedData['parent']    = $parent;


        $this->parsedData['dispatchedEmail'] = [
            'provider'             => DispatchedEmailProviderEnum::SES,
            'provider_dispatch_id' => $this->auroraModelData->{'Email Tracking SES Id'},
            'email_address'        => $this->auroraModelData->{'Email Tracking Email'},
            'state'                => $state,
            'fetched_at'           => now(),
            'last_fetched_at'      => now(),
            'source_id'            => $this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Key'},
            'created_at'           => $this->parseDatetime($this->auroraModelData->{'Email Tracking Created Date'}),
            'sent_at'              => $this->parseDatetime($this->auroraModelData->{'Email Tracking Sent Date'}),
            'first_read_at'        => $this->parseDatetime($this->auroraModelData->{'Email Tracking First Read Date'}),
            'last_read_at'         => $this->parseDatetime($this->auroraModelData->{'Email Tracking Last Read Date'}),
            'first_clicked_at'     => $this->parseDatetime($this->auroraModelData->{'Email Tracking First Clicked Date'}),
            'last_clicked_at'      => $this->parseDatetime($this->auroraModelData->{'Email Tracking Last Clicked Date'}),
            'number_reads'         => (int)$this->auroraModelData->{'Email Tracking Number Reads'},
            'number_clicks'        => (int)$this->auroraModelData->{'Email Tracking Number Clicks'},
        ];
    }


    /**
     * @throws \Throwable
     */
    public function parseProspectMailshot($recipient): Mailshot|null
    {
        $mailshot = Mailshot::where('source_alt_id', $this->organisation->id.':'.$this->auroraModelData->{'Email Tracking Published Email Template Key'})->first();

        if (!$mailshot) {
            $publishedEmailAuroraData = DB::connection('aurora')
                ->table('Published Email Template Dimension')
                ->where('Published Email Template Key', $this->auroraModelData->{'Email Tracking Published Email Template Key'})->first();


            $outbox = $recipient->shop->outboxes()->where('code', OutboxCodeEnum::INVITE)->first();

            $mailshotData = [
                'subject'           => $publishedEmailAuroraData->{'Published Email Template Subject'},
                'type'              => MailshotTypeEnum::INVITE,
                'state'             => MailshotStateEnum::SENT,
                'source_alt_id'     => $this->organisation->id.':'.$publishedEmailAuroraData->{'Published Email Template Key'},
                'created_at'        => $this->parseDatetime($this->auroraModelData->{'Email Tracking Created Date'}),
                'sent_at'           => $this->parseDatetime($this->auroraModelData->{'Email Tracking Sent Date'}),
                'recipients_recipe' => [],
                'fetched_at'        => now(),
                'last_fetched_at'   => now(),
            ];

            $mailshot = StoreMailshot::make()->action(
                outbox: $outbox,
                modelData: $mailshotData,
                hydratorsDelay: 60,
                strict: false,
                audit: false
            );
        }

        return $mailshot;
    }

    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Email Tracking Dimension')
            ->where('Email Tracking Key', $id)->first();
    }
}
