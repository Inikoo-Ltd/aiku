<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:42:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Media;
use App\Actions\Helpers\Images\GetPictureSources;
use Illuminate\Support\Arr;

/**
 * @property string $slug
 * @property string $shop_slug
 * @property string $department_slug
 * @property mixed $state
 * @property string $code
 * @property string $name
 * @property string $description
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property mixed $shop_code
 * @property mixed $shop_name
 * @property mixed $department_code
 * @property mixed $department_name
 * @property int $number_current_products
 * @property-read \App\Models\Helpers\Media|null $image
 * @property mixed $sales
 * @property mixed $sales_ly
 * @property mixed $invoices
 * @property mixed $invoices_ly
 * @property mixed $current_interval
 * @property mixed $organisation_name
 * @property mixed $organisation_slug
 * @property mixed $master_product_category_id
 * @property mixed $is_name_reviewed
 * @property mixed $sub_department_slug
 * @property mixed $sub_department_code
 * @property mixed $sub_department_name
 * @property mixed $is_description_title_reviewed
 * @property mixed $is_description_reviewed
 * @property mixed $is_description_extra_reviewed
 * @property mixed $collections
 * @property mixed $id
 * @property mixed $web_images
 * @property mixed $image_id
 * @property mixed $currency_code
 */
class FamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var \App\Models\Catalogue\ProductCategory $department */
        $imageSources = null;
        $media        = Media::find($this->image_id);
        if ($media) {
            $width        = 720;
            $height       = 720;
            $image        = $media->getImage()->resize($width, $height);
            $imageSources = GetPictureSources::run($image);
        }
        $collections = $this->collections ? json_decode($this->collections, true) : [];

        return [
            'id'                            => $this->id,
            'slug'                          => $this->slug,
            'shop_slug'                     => $this->shop_slug,
            'shop_code'                     => $this->shop_code,
            'shop_name'                     => $this->shop_name,
            'department_slug'               => $this->department_slug,
            'department_code'               => $this->department_code,
            'department_name'               => $this->department_name,
            'sub_department_slug'           => $this->sub_department_slug,
            'sub_department_code'           => $this->sub_department_code,
            'sub_department_name'           => $this->sub_department_name,
            'image'                         => $imageSources,
            'state'                         => [
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'code'                          => $this->code,
            'name'                          => $this->name,
            'description'                   => $this->description,
            'created_at'                    => $this->created_at,
            'updated_at'                    => $this->updated_at,
            'number_current_products'       => $this->number_current_products,
            'collections'                   => $collections,
            'sales'                         => $this->sales ?? 0,
            'sales_ly'                      => $this->sales_ly ?? 0,
            'sales_delta'                   => $this->calculateDelta($this->sales ?? 0, $this->sales_ly ?? 0),
            'currency_code'                 => $this->currency_code,
            'invoices'                      => $this->invoices ?? 0,
            'invoices_ly'                   => $this->invoices_ly ?? 0,
            'invoices_delta'                => $this->calculateDelta($this->invoices ?? 0, $this->invoices_ly ?? 0),
            'current_interval'              => $this->current_interval ?? 'ytd',
            'organisation_name'             => $this->organisation_name,
            'organisation_slug'             => $this->organisation_slug,
            'master_product_category_id'    => $this->master_product_category_id,
            'is_name_reviewed'              => $this->is_name_reviewed,
            'is_description_title_reviewed' => $this->is_description_title_reviewed,
            'is_description_reviewed'       => $this->is_description_reviewed,
            'is_description_extra_reviewed' => $this->is_description_extra_reviewed,
            'image_thumbnail'               => Arr::get($this->web_images, 'main.thumbnail'),
        ];
    }

    private function calculateDelta($current, $previous): ?array
    {
        if (!$previous || $previous == 0) {
            return null;
        }

        $delta = (($current - $previous) / $previous) * 100;

        return [
            'value'       => $delta,
            'formatted'   => number_format($delta, 1).'%',
            'is_positive' => $delta > 0,
            'is_negative' => $delta < 0,
        ];
    }
}
