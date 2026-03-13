<?php

/*
 *  Author: Jonathan lopez <raul@inikoo.com>
 *  Created: Sat, 22 Oct 2022 18:53:15 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, inikoo
 */

namespace App\Http\Resources\Dropshipping;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Helpers\NaturalLanguage;
use App\Models\Catalogue\Product;
use App\Models\Fulfilment\StoredItem;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property mixed $state
 * @property string $shop_slug
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_slug
 * @property mixed $department_code
 * @property mixed $department_name
 * @property mixed $family_slug
 * @property mixed $family_code
 * @property mixed $family_name
 * @property StoredItem|Product $item
 * @property mixed $margin
 * @property mixed $platform_product_id
 * @property mixed $item_description
 * @property mixed $id
 *
 */
class DropshippingBundlesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'product_id'                    => $this->product_id,
            'code'                  => $this->product_code,
            'name'                  => $this->product_name,
            'description'           => $this->product_description,
        ];
    }
}
