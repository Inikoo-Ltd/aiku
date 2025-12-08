<?php

/*
 * author Arya Permana - Kirin
 * created on 14-02-2025-11h-20m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Media;

use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class DetachRetinaAttachmentFromModel extends RetinaAction
{
    public function handle(PalletDelivery|PalletReturn $model, Media $attachment): PalletDelivery|PalletReturn
    {
        $model->attachments()->detach($attachment->id);

        return $model;
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, Media $attachment, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($palletDelivery, $attachment);
    }

    public function inPalletReturn(PalletReturn $palletReturn, Media $attachment, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($palletReturn, $attachment);
    }
}
