<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Http\Resources\Masters;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $group_id
 * @property mixed $master_shop_id
 * @property string $slug
 * @property string $code
 * @property string $description
 * @property bool $status
 * @property mixed $data
 */
class MasterCollectionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'slug'           => $this->slug,
            'code'           => $this->code,
            'name'           => $this->name,
            'description'    => $this->description,
            'state'          => $this->state,
            'products_status' => $this->products_status,
            'data'           => $this->data,
            'description_title'     => $this->description_title,
            'description_extra'     => $this->description_extra,
            'name_i8n'              => $this->getTranslations('name_i8n'),
            'description_i8n'       => $this->getTranslations('description_i8n'),
            'description_title_i8n' => $this->getTranslations('description_title_i8n'),
            'description_extra_i8n' => $this->getTranslations('description_extra_i8n'),
            'translation_box' => [
                'title' => __('Multi-language Translations'),
                'save_route' => [
                'name'       => 'grp.models.master_collection.translations.update',
                'parameters' => []
                ],
            ],
        ];
    }
}
