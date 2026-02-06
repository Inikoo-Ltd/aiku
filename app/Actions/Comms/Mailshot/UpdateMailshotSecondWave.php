<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Feb 2026 16:02:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotSecondWave extends OrgAction
{
    use WithActionUpdate;


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
