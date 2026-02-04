<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 3 Feb 2026 15:55:10 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
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
        // clone the mailshot
        $newMailshot = $originalMailshot->replicate();

        // Prepare data model for new mailshot
        $dataModel = [
            'parent_mailshot_id' => $originalMailshot->id,
            'date' => now(),
            'group_id' => $originalMailshot->group_id,
            'organisation_id' => $originalMailshot->organisation_id,
            'shop_id' => $originalMailshot->shop_id,
        ];

        $newMailshot = DB::transaction(function () use ($originalMailshot, $newMailshot, $dataModel) {
            /** @var Mailshot $newMailshot */
            $newMailshot = $originalMailshot->outbox->mailshots()->create($dataModel);
            $newMailshot->stats()->create();

            // Clone the email and its livesnapshot if exists
            if ($originalMailshot->email) {
                $originalEmail = $originalMailshot->email;
                $newEmail = $originalEmail->replicate();
                $newEmail->parent_id = $newMailshot->id;
                $newEmail->parent_type = Mailshot::class;
                $newEmail->save();

                // Clone the livesnapshot if exists
                if ($originalEmail->liveSnapshot) {
                    $originalSnapshot = $originalEmail->liveSnapshot;
                    $newSnapshot = $originalSnapshot->replicate();
                    $newSnapshot->parent_id = $newEmail->id;
                    $newSnapshot->parent_type = Email::class;
                    $newSnapshot->save();

                    // Update the new email to point to the cloned snapshot
                    $newEmail->live_snapshot_id = $newSnapshot->id;
                    $newEmail->save();
                }

                // Update the new mailshot to point to the cloned email
                $newMailshot->email_id = $newEmail->id;
                $newMailshot->save();
            }

            // Mark original mailshot as having active second wave
            $originalMailshot->is_second_wave_active = true;
            $originalMailshot->save();

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

        return $newMailshot;
    }

    public function action(Mailshot $mailshot): Mailshot
    {
        return $this->handle($mailshot);
    }
}
