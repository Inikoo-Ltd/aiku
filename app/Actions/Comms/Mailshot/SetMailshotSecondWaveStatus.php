<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 3 Feb 2026 16:21:24 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\Comms\Mailshot\UI\HasUIMailshots;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithOutboxBuilder;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use Lorisleiva\Actions\ActionRequest;

class SetMailshotSecondWaveStatus extends OrgAction
{
    // TODO: Check and make sure this condition is correct
    use HasUIMailshots;
    use WithActionUpdate;
    use WithCatalogueAuthorisation;
    use WithNoStrictRules;
    use WithOutboxBuilder;

    /**
     * @throws \Throwable
     */

    public function handle(Mailshot $originalMailshot, array $modelData): Mailshot
    {
        $isActive = $modelData['status'];

        // Only create second wave if activating and it doesn't exist yet
        if ($isActive && !$originalMailshot->secondWave) {
            (new CloneMailshotForSecondWave())->action($originalMailshot);
            $this->update($originalMailshot, ['is_second_wave_enabled' => true]);
        } else {
            $this->update($originalMailshot, ['is_second_wave_enabled' => $isActive]);
        }

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
        $this->initialisationFromShop($shop, $request);

        return $this->handle($mailshot, $this->validatedData);
    }
}
