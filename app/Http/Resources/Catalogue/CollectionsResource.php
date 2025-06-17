<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:19:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $shop_slug
 * @property string $code
 * @property string $name
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $number_departments
 * @property mixed $number_families
 * @property mixed $number_products
 * @property mixed $number_collections
 * @property mixed $state_webpage
 * @property mixed $url_webpage
 * @property mixed $website_slug
 * @property mixed $state
 */
class CollectionsResource extends JsonResource
{
    public function toArray($request): array
    {


        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'shop_slug'         => $this->shop_slug,
            'shop_code'         => $this->shop_code,
            'shop_name'         => $this->shop_name,
            'code'              => $this->code,
            'name'              => $this->name,
            'description'       => $this->description,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,

            'state'      => $this->state,
            'state_icon' => $this->state ? CollectionStateEnum::from($this->state->value)->stateIcon()[$this->state->value] : null,

            'number_departments'      => $this->number_departments,
            'number_families'         => $this->number_families,
            'number_products'         => $this->number_products,
            'number_collections'      => $this->number_collections,
            'state_webpage'           => $this->state_webpage,
            'state_webpage_icon'      => $this?->state_webpage ? WebpageStateEnum::from($this->state_webpage)->stateIcon()[$this->state_webpage] : null,
            'url_webpage'             => $this->url_webpage,
            'route_webpage_show'      => [
                'name'       => 'grp.org.shops.show.web.webpages.show',
                'parameters' => [
                    'organisation' => $this->organisation_slug,
                    'shop'         => $this->shop_slug,
                    'website'      => $this->website_slug,
                    'collection'   => $this->slug
                ],
                'method'     => 'get'
            ],
            'route_delete_collection' => [
                'name'       => 'grp.models.collection.delete',
                'parameters' => [
                    'collection' => $this->id
                ],
                'method'     => 'delete'
            ],
            'route_disable_webpage'   => [
                'name'       => 'grp.models.collection.webpage_disable',
                'parameters' => [
                    'collection' => $this->id
                ],
                'method'     => 'patch'
            ],


        ];
    }
}
