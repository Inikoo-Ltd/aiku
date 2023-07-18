<?php
/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\UniversalSearch;

use App\Http\Resources\Auth\UserSearchResultResource;
use App\Http\Resources\Web\WebsiteSearchResultResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $model_type
 */
class UniversalSearchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'model_type' => $this->model_type,
            'model'      => $this->when(true, function () {
                return match (class_basename($this->resource->model)) {
                    'Website' => new WebsiteSearchResultResource($this->resource->model),
                    'User'    => new UserSearchResultResource($this->resource->model),
                    default   => [],
                };
            }),

        ];
    }
}
