<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Mail;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

class EmailTemplateResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var \App\Models\Comms\EmailTemplate $emailTemplate */
        $emailTemplate = $this;

        $image = null;
        $imageThumbnail = null;
        if ($emailTemplate->screenshot) {
            $image = $emailTemplate->screenshot->getImage();
            $imageThumbnail = $emailTemplate->screenshot->getImage()->resize(0, 200);
        }

        return [
            'id' => $emailTemplate->id,
            'slug' => $emailTemplate->slug,
            'title' => $emailTemplate->name,
            'image' => $image ? GetPictureSources::run($image) : null,
            'image_thumbnail' => $imageThumbnail ? GetPictureSources::run($imageThumbnail) : null,
        ];
    }
}
