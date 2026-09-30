<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 3 Feb 2026 16:21:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Mailshot\MailshotStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetMailshotSecondWaveStatus extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Mailshot $originalMailshot, array $modelData): Mailshot
    {
        $isActive     = $modelData['status'];
        $parentIsOpen = in_array($originalMailshot->state, [MailshotStateEnum::IN_PROCESS, MailshotStateEnum::READY]);

        if ($isActive && !$parentIsOpen) {
            throw ValidationException::withMessages([
                'status' => __('The 2nd wave can only be switched on before the email is scheduled or sent.'),
            ]);
        }

        if ($isActive) {
            if (!$originalMailshot->secondWave) {
                (new CloneMailshotForSecondWave())->action($originalMailshot);
            }
            $this->update($originalMailshot, ['is_second_wave_enabled' => true]);

            return $originalMailshot->refresh();
        }

        $secondWave = $originalMailshot->secondWave;

        if ($secondWave && !$parentIsOpen) {
            DB::transaction(function () use ($secondWave, $originalMailshot) {
                $lockedSecondWave = Mailshot::whereKey($secondWave->id)->lockForUpdate()->first();

                if (!$lockedSecondWave || $lockedSecondWave->state !== MailshotStateEnum::READY || $lockedSecondWave->start_sending_at !== null) {
                    throw ValidationException::withMessages([
                        'status' => __('The 2nd wave has already started sending and cannot be cancelled.'),
                    ]);
                }

                DeleteMailshotSecondWave::run($lockedSecondWave);
                $this->update($originalMailshot, ['is_second_wave_enabled' => false]);
            });

            return $originalMailshot->refresh();
        }

        $this->update($originalMailshot, ['is_second_wave_enabled' => false]);

        return $originalMailshot->refresh();
    }


    public function rules(): array
    {
        $rules = [
            'status' => ['required', 'boolean'],
        ];

        return $rules;
    }


    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, Mailshot $mailshot, ActionRequest $request): Mailshot
    {
        abort_unless($mailshot->shop_id === $shop->id, 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
