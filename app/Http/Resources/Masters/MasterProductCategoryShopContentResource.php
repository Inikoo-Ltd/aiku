<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Masters;

use App\Enums\Web\Webpage\WebpageTypeEnum;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property string|null $description_title
 * @property string|null $description_extra
 * @property bool $follow_master
 * @property string $shop_slug
 * @property string $shop_code
 * @property string $shop_name
 * @property \App\Models\Web\Webpage|null $webpage
 */
class MasterProductCategoryShopContentResource extends JsonResource
{
    /**
     * The read only counterpart of the fields edited in
     * \App\Actions\Web\Webpage\UI\EditWebpage.
     *
     * @return array<string, mixed>
     */
    private function getWebpageData(Webpage $webpage): array
    {
        $isBlog = $webpage->type == WebpageTypeEnum::BLOG;

        return [
            'id'               => $webpage->id,
            'slug'             => $webpage->slug,
            'state'            => $webpage->state,
            'code'             => $webpage->code,
            'url'              => $webpage->url,
            'url_prefix'       => $isBlog
                ? 'https://'.$webpage->website->domain.'/blog/'
                : 'https://'.$webpage->website->domain.'/',
            'full_url'         => $webpage->getUrl(),
            'canonical_url'    => $webpage->getCanonicalUrl(),
            'breadcrumb_label' => $webpage->breadcrumb_label,
            'title'            => $webpage->title,
            'description'      => $webpage->description,
            'title_prefix'     => data_get($webpage->settings, 'webpage.title_prefix'),
            'title_suffix'     => data_get($webpage->settings, 'webpage.title_suffix'),
            'show_price'       => (bool)data_get($webpage->settings, 'webpage.show_price', false),
            'index_page'       => $webpage->index_page ?? true,
            'follow_link'      => $webpage->follow_link ?? true,
            'seo_image'        => $webpage->imageSources(1200, 1200, 'seoImage'),
            'seo_image_alt'    => Arr::get($webpage->seo_data, 'image_alt'),
            'structured_data'  => Arr::get($webpage->seo_data, 'structured_data'),
        ];
    }

    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'description_title' => $this->description_title,
            'description_extra' => $this->description_extra,
            'follow_master'     => (bool)$this->follow_master,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
            'shop_name'         => $this->shop_name,
            'webpage'           => $this->webpage ? $this->getWebpageData($this->webpage) : null,
        ];
    }
}
