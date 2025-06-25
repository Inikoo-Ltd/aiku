<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 May 2025 01:54:52 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Accounting\MitSavedCard;

use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Accounting\MitSavedCard;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class SetAsDefaultRetinaMitSavedCard extends RetinaAction
{
    use WithActionUpdate;

    public function handle(MitSavedCard $mitSavedCard, array $modelData): MitSavedCard
    {
        data_set($modelData, 'priority', 1);

        if (Arr::get($modelData, 'priority') === 1) {
            $this->customer->mitSavedCard()->where('priority', 1)
                ->where('id', '!=', $mitSavedCard->id)
                ->update(['priority' => $mitSavedCard->priority]);
        }

        return $this->update($mitSavedCard, $modelData);
    }

    public function asController(MitSavedCard $mitSavedCard, ActionRequest $request): MitSavedCard
    {
        $this->initialisation($request);

        return $this->handle($mitSavedCard, $this->validatedData);
    }
}
