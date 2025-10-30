<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:19:49 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Traits\ParsesCollectionParentsData;
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
 * @property mixed $products_status
 * @property mixed $website_slug
 * @property mixed $state
 * @property mixed $webpage_state
 * @property mixed $webpage_url
 * @property mixed $webpage_slug
 * @property mixed $number_parents
 * @property mixed $parents_data
 * @property mixed $organisation_code
 * @property mixed $master_collection_id
 */
class CollectionsResource extends JsonResource
{
    use ParsesCollectionParentsData;

    public function toArray($request): array
    {
        $urlMaster = null;
        if ($this->master_collection_id) {
            $urlMaster = [
                'name'       => 'grp.helpers.redirect_master_collection',
                'parameters' => [
                    $this->master_collection_id
                ]
            ];
        }

        return [
            'id'                   => $this->id,
            'slug'                 => $this->slug,
            'shop_slug'            => $this->shop_slug,
            'shop_code'            => $this->shop_code,
            'shop_name'            => $this->shop_name,
            'code'                 => $this->code,
            'name'                 => $this->name,
            'description'          => $this->description,
            'created_at'           => $this->created_at,
            'updated_at'           => $this->updated_at,
            'organisation_name'    => $this->organisation_name,
            'organisation_slug'    => $this->organisation_slug,
            'organisation_code'    => $this->organisation_code,
            'website_slug'         => $this->website_slug,
            'master_collection_id' => $this->master_collection_id,
            'url_master'           => $urlMaster,

            'state'           => $this->state,
            'state_icon'      => $this->state ? CollectionStateEnum::from($this->state->value)->stateIcon()[$this->state->value] : null,
            'products_status' => $this->products_status,

            'number_parents'          => $this->number_parents,
            'number_families'         => $this->number_families,
            'number_products'         => $this->number_products,
            'webpage_state'           => $this->webpage_state,
            'webpage_state_icon'      => $this->webpage_state ? WebpageStateEnum::from($this->webpage_state)->stateIcon()[$this->webpage_state] : null,
            'webpage_state_label'     => $this->webpage_state ? WebpageStateEnum::from($this->webpage_state)->labels()[$this->webpage_state] : null,
            'webpage_url'             => $this->webpage_url,
            'webpage_slug'            => $this->webpage_slug,
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
            'route_enable_webpage'    => [
                'name'       => 'grp.models.collection.webpage_enable',
                'parameters' => [
                    'collection' => $this->id
                ],
                'method'     => 'patch'
            ],
            'parents_data'            => $this->parseCollectionParentsData($this->parents_data),

        ];
    }

}
