<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 3 Feb 2026 15:55:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithOutboxBuilder;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Facades\DB;

class CloneMailshotForSecondWave extends OrgAction
{
    use HasUIMailshots;
    use WithCatalogueAuthorisation;
    use WithNoStrictRules;
    use WithOutboxBuilder;

    /**
     * @throws \Throwable
     */
    public function handle(Mailshot $parentMailshot): Mailshot
    {
        // TODO: Check and make sure this block code
        $dataModel = [
            'date' => $parentMailshot->date,
            'group_id' => $parentMailshot->group_id,
            'organisation_id' => $parentMailshot->organisation_id,
            'shop_id' => $parentMailshot->shop_id,
            'type' => $parentMailshot->type,
            'subject' => $parentMailshot->subject . ' (2nd)',
            'state' => $parentMailshot->state,
            'is_second_wave' => true,
            'ready_at' => $parentMailshot->ready_at,
            'outbox_id' => $parentMailshot->outbox_id,
            'recipients_recipe' => $parentMailshot->recipients_recipe,
        ];

        $secondWaveMailshot = DB::transaction(function () use ($parentMailshot, $dataModel) {
            /** @var Mailshot $newMailshot */
            $newMailshot = $parentMailshot->secondWave()->create($dataModel);

            $newMailshot->stats()->create();

            // call StoreEmail with data from original mailshot
            $parentEmail = $parentMailshot->email;

            StoreEmail::make()->action(
                $newMailshot,
                null, // no email template, using existing email data
                modelData: [
                    'subject' => $newMailshot->subject,
                    'builder' => $parentEmail->builder,
                    'layout' => $parentEmail->liveSnapshot?->layout ?? $parentEmail->unpublishedSnapshot?->layout,
                    'compiled_layout' => $parentEmail->liveSnapshot?->compiled_layout,
                    'snapshot_state' => $parentEmail->liveSnapshot?->state ?? $parentEmail->unpublishedSnapshot?->state,
                    'snapshot_published_at' => $parentEmail->liveSnapshot?->published_at ??  $parentEmail->unpublishedSnapshot?->published_at,
                    'snapshot_recyclable' => $parentEmail->liveSnapshot?->recyclable ?? $parentEmail->unpublishedSnapshot?->recyclable ?? false,
                    'snapshot_first_commit' => $parentEmail->liveSnapshot?->first_commit ?? $parentEmail->unpublishedSnapshot?->first_commit ?? true,
                ],
                strict: false
            );

            return $newMailshot;
        });

        return $secondWaveMailshot;
    }

    public function action(Mailshot $mailshot): Mailshot
    {
        return $this->handle($mailshot);
    }
}
