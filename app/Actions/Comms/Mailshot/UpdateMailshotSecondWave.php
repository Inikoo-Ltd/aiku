<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 4 Feb 2026 16:02:51 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Http\Resources\Mail\MailshotResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateMailshotSecondWave extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;


    public function handle(Mailshot $parentMailshot, array $modelData): Mailshot
    {
        $secondwave = $parentMailshot->secondWave;
        if (!$secondwave) {
            throw new \Exception('Second wave not found');
        }

        if ($secondwave->state !== MailshotStateEnum::READY || $secondwave->start_sending_at !== null) {
            throw ValidationException::withMessages([
                'subject' => __('The 2nd wave has already started sending and can no longer be edited.'),
            ]);
        }

        $isSubjectChanged = $modelData['subject'] !== $secondwave->subject;

        if ($isSubjectChanged) {
            data_set($modelData, 'data.subject_edited_by_user', true);
        }

        $mailshot = $this->update($secondwave, $modelData, ['data']);

        // update subject if changed
        if ($isSubjectChanged && $mailshot->email) {
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
        abort_unless($mailshot->shop_id === $shop->id, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }


    public function jsonResponse(Mailshot $mailshot): MailshotResource
    {
        return new MailshotResource($mailshot);
    }
}
