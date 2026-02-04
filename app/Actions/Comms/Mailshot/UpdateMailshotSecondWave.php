<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Feb 2026 16:02:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateMailshots;
use App\Actions\Comms\Outbox\Hydrators\OutboxHydrateMailshots;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateMailshots;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateMailshots;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotSecondWave extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(Mailshot $parentMailshot, array $modelData): Mailshot
    {
        $secondwave = $parentMailshot->secondWave;
        if (!$secondwave) {
            throw new \Exception('Second wave not found');
        }
        $mailshot = $this->update($secondwave, $modelData, ['data']);

        // update subject if changed
        if ($mailshot->wasChanged('subject') && $mailshot->email) {
            $mailshot->email->update(['subject' => $mailshot->subject]);
        }

        // TODO: check it latter
        // if ($mailshot->wasChanged('state')) {
        //     GroupHydrateMailshots::dispatch($mailshot->group)->delay($this->hydratorsDelay);
        //     OrganisationHydrateMailshots::dispatch($mailshot->organisation)->delay($this->hydratorsDelay);
        //     OutboxHydrateMailshots::dispatch($mailshot->outbox)->delay($this->hydratorsDelay);
        //     ShopHydrateMailshots::dispatch($mailshot->shop)->delay($this->hydratorsDelay);
        // }

        return $mailshot;
    }



    public function rules(): array
    {
        $rules = [
            'subject'           => ['required', 'string', 'max:255'],
            'send_delay_hours'  => ['required', 'integer', 'min:1'],
        ];

        return $rules;
    }

    // public function action(Mailshot $mailshot, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Mailshot
    // {
    //     $this->strict = $strict;
    //     if (!$audit) {
    //         Mailshot::disableAuditing();
    //     }
    //     $this->asAction       = true;
    //     $this->hydratorsDelay = $hydratorsDelay;
    //     $this->initialisationFromShop($mailshot->shop, $modelData);

    //     return $this->handle($mailshot, $this->validatedData);
    // }
    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }


    public function jsonResponse(Mailshot $mailshot): MailshotResource
    {
        return new MailshotResource($mailshot);
    }
}
