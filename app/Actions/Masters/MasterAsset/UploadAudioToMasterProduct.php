<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 10:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Helpers\Media;
use App\Models\Masters\MasterAsset;
use Lorisleiva\Actions\ActionRequest;

class UploadAudioToMasterProduct extends OrgAction
{
    use WithAttachMediaToModel;

    public function handle(MasterAsset $masterAsset, array $modelData): Media
    {
        $audioFile = $modelData['audio'];

        $media = StoreMediaFromFile::run(
            $masterAsset,
            [
                'path'         => $audioFile->getPathName(),
                'originalName' => $audioFile->getClientOriginalName(),
                'extension'    => $audioFile->guessClientExtension(),
                'checksum'     => md5_file($audioFile->getPathName()),
            ],
            'audio',
            'audio'
        );

        $this->attachMediaToModel($masterAsset, $media, 'audio');

        UpdateMasterProductImages::make()->handle($masterAsset, ['audio_id' => $media->id], true);

        return $media;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,oga,m4a,aac,flac', 'max:50000'],
        ];
    }

    public function asController(MasterAsset $masterAsset, ActionRequest $request): void
    {
        $this->initialisationFromGroup($masterAsset->group, $request);

        $this->handle($masterAsset, $this->validatedData);
    }
}
