<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 3 Feb 2026 15:55:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Email\StoreEmail;
use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithOutboxBuilder;
use App\Enums\Comms\Mailshot\MailshotTypeEnum;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Models\Comms\Email;
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
    public function handle(Mailshot $originalMailshot): Mailshot
    {
        // TODO: Check and make sure this block code
        $dataModel = [
            'date' => $originalMailshot->date,
            'group_id' => $originalMailshot->group_id,
            'organisation_id' => $originalMailshot->organisation_id,
            'shop_id' => $originalMailshot->shop_id,
            'type' => $originalMailshot->type,
            'subject' => $originalMailshot->subject . ' (2nd)',
            'state' => $originalMailshot->state,
            'is_second_wave' => true,
            'outbox_id' => $originalMailshot->outbox_id,
            'recipients_recipe' => $originalMailshot->recipients_recipe,
        ];

        $newMailshotparent = DB::transaction(function () use ($originalMailshot, $dataModel) {
            /** @var Mailshot $newMailshot */
            $newMailshot = $originalMailshot->secondWave()->create($dataModel);

            $newMailshot->stats()->create();

            // call StoreEmail with data from original mailshot
            $originalEmail = $originalMailshot->email;

            StoreEmail::make()->action(
                $newMailshot,
                null, // no email template, using existing email data
                modelData: [
                    'subject' => $originalEmail->subject,
                    'builder' => $originalEmail->builder,
                    'layout' => $originalEmail->liveSnapshot?->layout ?? $originalEmail->unpublishedSnapshot?->layout,
                    'compiled_layout' => $originalEmail->liveSnapshot?->compiled_layout,
                    'snapshot_state' => $originalEmail->unpublishedSnapshot?->state ?? $originalEmail->liveSnapshot?->state,
                    'snapshot_published_at' => $originalEmail->unpublishedSnapshot?->published_at ?? $originalEmail->liveSnapshot?->published_at,
                    'snapshot_recyclable' => $originalEmail->unpublishedSnapshot?->recyclable ?? $originalEmail->liveSnapshot?->recyclable ?? false,
                    'snapshot_first_commit' => $originalEmail->unpublishedSnapshot?->first_commit ?? $originalEmail->liveSnapshot?->first_commit ?? true,
                ],
                strict: false
            );

            return $newMailshot;
        });


        //  TODO: Check later, how it work
        // GroupHydrateMailshots::dispatch($originalMailshot->group)->delay($this->hydratorsDelay);
        // OrganisationHydrateMailshots::dispatch($originalMailshot->organisation)->delay($this->hydratorsDelay);
        // OutboxHydrateMailshots::dispatch($originalMailshot->outbox)->delay($this->hydratorsDelay);
        // ShopHydrateMailshots::dispatch($originalMailshot->shop)->delay($this->hydratorsDelay);

        // $outboxCode = match ($newMailshot->type) {
        //     MailshotTypeEnum::MARKETING => OutboxCodeEnum::MARKETING,
        //     MailshotTypeEnum::NEWSLETTER => OutboxCodeEnum::NEWSLETTER,
        //     MailshotTypeEnum::INVITE => OutboxCodeEnum::INVITE,
        //     MailshotTypeEnum::ABANDONED_CART => OutboxCodeEnum::ABANDONED_CART,
        //     default => OutboxCodeEnum::NEWSLETTER,
        // };

        // create email
        // $this->createMailShotEmail($originalMailshot->shop, $outboxCode, $newMailshot, $originalMailshot->outbox);

        return $newMailshotparent;
    }

    public function action(Mailshot $mailshot): Mailshot
    {
        return $this->handle($mailshot);
    }
}
