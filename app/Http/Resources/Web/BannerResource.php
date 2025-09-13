<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 20:01:56 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Helpers\Images\GetPictureSources;
use App\Enums\Web\Banner\BannerStateEnum;
use App\Http\Resources\HasSelfCall;
use App\Http\Resources\Helpers\SnapshotResource;
use App\Models\Web\Banner;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $live_snapshot_id
 */
class BannerResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        /** @var Banner $banner */
        $banner = $this;

        $image          = null;
        $imageThumbnail = null;
        if ($banner->image) {
            $image          = $banner->image->getImage();
            $imageThumbnail = $banner->image->getImage()->resize(0, 48);
        }

        $publishedSnapshot = [];
        if ($banner->state == BannerStateEnum::LIVE and $this->live_snapshot_id) {
            $snapshot          = $banner->liveSnapshot;
            $publishedSnapshot = SnapshotResource::make($snapshot)->getArray();
        }


        return [
            'id'                 => $banner->id,
            'type'               => $banner->type,
            'ulid'               => $banner->ulid,
            'slug'               => $banner->slug,
            'name'               => $banner->name,
            'state_value'        => $banner->state->value,
            'state'              => $banner->state,
            'state_label'        => $banner->state->labels()[$banner->state->value],
            'state_icon'         => $banner->state->stateIcon()[$banner->state->value],
            'image_thumbnail'    => $imageThumbnail ? GetPictureSources::run($imageThumbnail) : null,
            'image'              => $image ? GetPictureSources::run($image) : null,
            'route'              => [
                'name'       => 'customer.banners.banners.show',
                'parameters' => [$banner->slug]
            ],
            'updated_at'         => $banner->updated_at,
            'created_at'         => $banner->created_at,
            'workshopRoute'      => [
                'name'       => 'customer.banners.banners.workshop',
                'parameters' => [$banner->slug]
            ],
            'compiled_layout'    => $banner->compiled_layout,
            'delivery_url'       => config('app.delivery_url').'/banners/'.$banner->ulid,
            'published_snapshot' => $publishedSnapshot,
            'views'              => $banner->stats?->number_views,
        ];
    }
}
