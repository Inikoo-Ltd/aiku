<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 20:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UploadVideoToModelHasWebBlocks extends OrgAction
{
    use WithWebEditAuthorisation;

    public const int MAX_VIDEO_KB = 50 * 1024;

    /**
     * @return array{platform: string, video_url: string, poster_url: null, permalink: null}
     */
    public function handle(UploadedFile $video): array
    {
        $extension = $video->getMimeType() === 'video/webm' ? 'webm' : 'mp4';

        return [
            'platform'   => 'upload',
            'video_url'  => StoreWebBlockVideoFile::run($video->getPathname(), $extension),
            'poster_url' => null,
            'permalink'  => null,
        ];
    }

    public function rules(): array
    {
        return [
            'video' => ['required', File::types(['mp4', 'webm'])->max(self::MAX_VIDEO_KB)],
        ];
    }

    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): array
    {
        $this->initialisationFromShop($modelHasWebBlocks->shop, $request);

        return $this->handle($this->validatedData['video']);
    }
}
