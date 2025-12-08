<?php

/*
 * author Arya Permana - Kirin
 * created on 14-02-2025-11h-14m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Media;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\RetinaAction;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Fulfilment\PalletReturn;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AttachRetinaAttachmentToModel extends RetinaAction
{
    private PalletDelivery|PalletReturn $parent;

    public function handle(PalletDelivery|PalletReturn $model, array $modelData): void
    {
        foreach (Arr::get($modelData, 'attachments') as $attachment) {
            $file = $attachment;
            $attachmentData = [
                'path' => $file->getPathName(),
                'originalName' => $file->getClientOriginalName(),
                'scope' => $modelData['scope'],
                'caption' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'extension' => $file->getClientOriginalExtension(),
            ];

            SaveModelAttachment::make()->action($model, $attachmentData);
        }
    }

    public function rules(): array
    {
        $allowedScopes = ['Other'];

        return [
            'attachments' => ['required', 'array'],
            'attachments.*' => ['required', 'file', 'max:50000'],
            'scope' => [
                'required',
                'string',
                Rule::in($allowedScopes),
            ],
        ];
    }

    public function inPalletDelivery(PalletDelivery $palletDelivery, ActionRequest $request): void
    {
        $this->parent = $palletDelivery;
        $this->initialisation($request);

        $this->handle($palletDelivery, $this->validatedData);
    }

    public function inPalletReturn(PalletReturn $palletReturn, ActionRequest $request): void
    {
        $this->parent = $palletReturn;
        $this->initialisation($request);

        $this->handle($palletReturn, $this->validatedData);
    }
}
