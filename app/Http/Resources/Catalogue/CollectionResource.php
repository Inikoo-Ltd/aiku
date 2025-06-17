<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:27:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray($request): array
    {

        // /** @var Collection $collection */
        $collection = $this;

        return [
            'id'                => $collection->id,
            'slug'              => $collection->slug,
            'state'             =>  $collection->state,
            'state_icon'             =>  CollectionStateEnum::from($collection->state)->stateIcon()[$collection->state],
            'shop'              => $collection->shop_slug,
            'code'              => $collection->code,
            'name'              => $collection->name,
            'description'       => html_entity_decode(strip_tags($collection->description)),
            'created_at'        => $collection->created_at,
            'updated_at'        => $collection->updated_at,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
            'shop_name'         => $this->shop_name,
            'shop_slug'         => $this->shop_slug,
            'number_departments' => $this->number_departments,
            'number_families' => $this->number_families,
            'number_products' => $this->number_products,
            'number_collections' => $this->number_collections,
            'state_webpage' => $collection->state_webpage,
            'state_webpage_icon' => $collection?->state_webpage ? WebpageStateEnum::from($collection->state_webpage)->stateIcon()[$collection->state_webpage] : null,
            'url_webpage' => $collection->url_webpage,
            'route_webpage_show' => [
                'name'       => 'grp.org.shops.show.web.webpages.show',
                'parameters' => [
                    'organisation' => $collection->organisation_slug,
                    'shop'        => $collection->shop_slug,
                    'website'     => $collection->website_slug,
                    'collection'  => $collection->slug
                ],
                'method'     => 'get'
            ],
            'route_delete_collection' => [
                'name'       => 'grp.models.collection.delete',
                'parameters' => [
                    'collection' => $collection->id
                ],
                'method'     => 'delete'
            ],
            'route_disable_webpage' => [
                'name'       => 'grp.models.collection.webpage_disable',
                'parameters' => [
                    'collection' => $collection->id
                ],
                'method'     => 'patch'
            ],

        ];
    }
}
