<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 11 Jul 2023 12:17:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Web;

use App\Actions\Web\Webpage\WithGetWebpageWebBlocks;
use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Http\Resources\HasSelfCall;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property mixed $id
 */
class WebpageResource extends JsonResource
{
    use HasSelfCall;
    use WithGetWebpageWebBlocks;

    public function toArray($request): array
    {
        /** @var Webpage $webpage */
        $webpage = Webpage::find($this->id);

        $webPageLayout = $webpage->unpublishedSnapshot?->layout ?: ['web_blocks' => []];
        $webPageLayout['web_blocks'] = $this->getWebBlocks($webpage, Arr::get($webPageLayout, 'web_blocks'));
        $modelId = null;
        if($webpage->model_type == 'Product') {
            $modelId = $webpage->model->family_id;
        } else {
            $modelId = $webpage->model_id;
        }

        return [
            'id'                  => $webpage->id,
            'slug'                => $webpage->slug,
            'level'               => $webpage->level,
            'domain'              => $webpage->website->domain ?? null,
            'website_layout'      => Arr::get($webpage->website->published_layout, 'theme.layout', 'blog'),
            'code'                => $webpage->code,
            'model_id'                               => $webpage->model_id,
            'product_category_id'                    => $modelId,
            // 'url'                 => $webpage->url,
            'url'                 => $webpage->getUrl(),
            'type'                => $webpage->type,
            'typeIcon'            => match ($webpage->type) {
                WebpageTypeEnum::STOREFRONT => ['fal', 'fa-home'],
                WebpageTypeEnum::OPERATIONS => ['fal', 'fa-ufo-beam'],
                WebpageTypeEnum::BLOG       => ['fal', 'fa-newspaper'],
                default                     => ['fal', 'fa-browser']
            },
            'is_dirty'                   => $webpage->is_dirty,
            'web_blocks_parameters'      => WebBlockParametersResource::collection($webpage->webBlocks),
            'layout'                     => $webPageLayout,
            'sub_type'                   => $webpage->sub_type,
            'created_at'                 => $webpage->created_at,
            'updated_at'                 => $webpage->updated_at,
            'state'                      => $webpage->state,
            'add_web_block_route'        => [
                'name'       => 'grp.models.webpage.web_block.store',
                'parameters' => $webpage->id
            ],
            'update_model_has_web_blocks_route'        => [
                'name'       => 'grp.models.model_has_web_block.update',
            ],
            'update_bulk_model_has_web_blocks_route'        => [
                'name'       => 'grp.models.model_has_web_block.bulk.update',
            ],
            'delete_model_has_web_blocks_route'        => [
                'name'       => 'grp.models.model_has_web_block.delete',
            ],
            'images_upload_route' => [
                'name'       => 'grp.models.model_has_web_block.images.store',
            ],
            'reorder_web_blocks_route'        => [
                'name'       => 'grp.models.webpage.reorder_web_blocks',
                'parameters' => $webpage->id
            ],
        ];
    }

}
