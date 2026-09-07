<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Aug 2026 10:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\Helpers\Media\StoreMediaFromFile;
use App\Actions\OrgAction;
use App\Actions\Traits\WithAttachMediaToModel;
use App\Models\Goods\TradeUnit;
use App\Models\Helpers\Media;
use Lorisleiva\Actions\ActionRequest;

class UploadAudioToTradeUnit extends OrgAction
{
    use WithAttachMediaToModel;

    public function handle(TradeUnit $tradeUnit, array $modelData): Media
    {
        $audioFile = $modelData['audio'];

        $media = StoreMediaFromFile::run(
            $tradeUnit,
            [
                'path'         => $audioFile->getPathName(),
                'originalName' => $audioFile->getClientOriginalName(),
                'extension'    => $audioFile->guessClientExtension(),
                'checksum'     => md5_file($audioFile->getPathName()),
            ],
            'audio',
            'audio'
        );

        $this->attachMediaToModel($tradeUnit, $media, 'audio');

        UpdateTradeUnitImages::make()->handle($tradeUnit, ['audio_id' => $media->id], true);

        return $media;
    }

    public function rules(): array
    {
        return [
            'audio' => ['required', 'file', 'mimes:mp3,wav,ogg,oga,m4a,aac,flac', 'max:50000'],
        ];
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisationFromGroup($tradeUnit->group, $request);

        $this->handle($tradeUnit, $this->validatedData);
    }
}
