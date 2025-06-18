<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 30 May 2025 16:59:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Web\Webpage\UI\IndexChangesWebpages;
use App\Actions\Web\Webpage\WithGetWebpageWebBlocks;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 */
class WebpageWorkshopResource extends JsonResource
{
    use HasSelfCall;
    use WithGetWebpageWebBlocks;

    public function toArray($request): array
    {
        /** @var Webpage $webpage */
        $webpage = Webpage::find($this->id);

        $webPageLayout               = $webpage->unpublishedSnapshot?->layout ?: ['web_blocks' => []];
        $webPageLayout['web_blocks'] = $this->getWebBlocks($webpage, Arr::get($webPageLayout, 'web_blocks'));

        return [
            'id'                                     => $webpage->id,
            'slug'                                   => $webpage->slug,
            'level'                                  => $webpage->level,
            'domain'                                 => $webpage->website->domain ?? null,
            'website_layout'                         => Arr::get($webpage->website->published_layout, 'theme.layout', 'blog'),
            'code'                                   => $webpage->code,
            'url'                                    => $webpage->url,
            'type'                                  => $webpage->type,
            'allow_fetch'                         => $webpage->allow_fetch,
            'route_webpage_edit' => [
                'name'       => 'grp.models.shop.webpage.update',
                'parameters' => [
                    'shop'         => $webpage->shop->id,
                    'webpage'      => $webpage->id
                ],
                'method' => 'patch'
            ],
            'typeIcon'                               => match ($webpage->type) {
                WebpageTypeEnum::STOREFRONT => ['fal', 'fa-home'],
                WebpageTypeEnum::OPERATIONS => ['fal', 'fa-ufo-beam'],
                WebpageTypeEnum::BLOG => ['fal', 'fa-newspaper'],
                default => ['fal', 'fa-browser']
            },
            'changes_webpage'                        => $this?->resource ? WebpagesResource::collection(IndexChangesWebpages::make()->handle($this->resource))->toArray(request()) : null,
            'is_dirty'                               => $webpage->is_dirty,
            'layout'                                 => $webPageLayout,
            'sub_type'                               => $webpage->sub_type,
            'created_at'                             => $webpage->created_at,
            'updated_at'                             => $webpage->updated_at,
            'state'                                  => $webpage->state,
            'add_web_block_route'                    => [
                'name'       => 'grp.models.webpage.web_block.store',
                'parameters' => $webpage->id
            ],
            'update_model_has_web_blocks_route'      => [
                'name' => 'grp.models.model_has_web_block.update',
            ],
            'update_bulk_model_has_web_blocks_route' => [
                'name' => 'grp.models.model_has_web_block.bulk.update',
            ],
            'delete_model_has_web_blocks_route'      => [
                'name' => 'grp.models.model_has_web_block.delete',
            ],
            'images_upload_route'                    => [
                'name' => 'grp.models.model_has_web_block.images.store',
            ],
            'reorder_web_blocks_route'               => [
                'name'       => 'grp.models.webpage.reorder_web_blocks',
                'parameters' => $webpage->id
            ],
        ];
    }



}
