<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Oct 2023 09:10:43 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\Banner;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $websites
 */
class BannersResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Banner $banner */
        $banner = $this;

        $image = null;
        $imageThumbnail = null;
        if ($banner->image) {
            $image = $banner->image->getImage();
            $imageThumbnail = $banner->image->getImage()->resize(0, 48);
        }

        return [
            'id' => $banner->id,
            'type' => $banner->type,
            'slug' => $banner->slug,
            'name' => $banner->name,
            'state' => $banner->state,
            'state_label' => $banner->state->labels()[$banner->state->value],
            'state_icon' => $banner->state->stateIcon()[$banner->state->value],
            'date_icon' => $banner->state->dateIcon()[$banner->state->value],
            'image_thumbnail' => $imageThumbnail ? GetPictureSources::run($imageThumbnail) : null,
            'image' => $image ? GetPictureSources::run($image) : null,
            'websites' => json_decode($this->websites),
            'date' => $banner->date,
            'delivery_url' => config('app.delivery_url').'/banners/'.$banner->ulid,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name' => $this->shop_name,
            'shop_slug' => $this->shop_slug,

        ];
    }
}
