<?php

/*
 * author Arya Permana - Kirin
 * created on 09-05-2025-11h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Accounting\MitSavedCard;

use App\Actions\RetinaAction;
use App\Models\Accounting\MitSavedCard;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteMitSavedCard extends RetinaAction
{
    use AsController;
    use WithAttributes;

    public function handle(MitSavedCard $mitSavedCard): void
    {
        $mitSavedCard->delete();
    }

    public function asController(MitSavedCard $mitSavedCard, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($mitSavedCard);
    }
}
