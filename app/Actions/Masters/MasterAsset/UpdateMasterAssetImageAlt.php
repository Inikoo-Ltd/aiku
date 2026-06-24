<?php

/*
 * Author: Rifqi Taufiqurrohman <rifqitaufiqurrohman1@gmail.com>
 * Created: Thu, 07 May 2026 Asia/Jakarta
 * Copyright (c) 2026, Inikoo
*/

namespace App\Actions\Masters\MasterAsset;

use App\Actions\GrpAction;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UpdateMasterAssetImageAlt extends GrpAction
{
    public function handle(MasterAsset $masterAsset, Media $media, array $modelData): MasterAsset
    {
        $masterAsset->images()->updateExistingPivot($media->id, [
            'caption' => $modelData['alt'] ?? null,
        ]);

        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function asController(MasterAsset $masterAsset, Media $media, ActionRequest $request): void
    {
        $this->initialisation($masterAsset->group, $request);

        $this->handle($masterAsset, $media, $this->validatedData);
    }
}
