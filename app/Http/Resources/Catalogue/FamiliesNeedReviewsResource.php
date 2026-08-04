<?php

/*
 * Author Louis Perez
 * Created on 03-08-2026-15h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Http\Resources\Catalogue;

use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Helpers\Media;
use App\Actions\Helpers\Images\GetPictureSources;

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
 * @property mixed $health_rank
 * @property mixed $webpage_state
 */
class FamiliesNeedReviewsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var ProductCategory $department */
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
            'code'                          => $this->code,
            'name'                          => $this->name,
            'state'                         => [
                'tooltip' => $this->state->labels()[$this->state->value],
                'icon'    => $this->state->stateIcon()[$this->state->value]['icon'],
                'class'   => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'description'                   => $this->description,
            'created_at'                    => $this->created_at,
            'image'                         => $imageSources,
            'updated_at'                    => $this->updated_at,
            'is_name_reviewed'              => $this->is_name_reviewed,
            'is_description_title_reviewed' => $this->is_description_title_reviewed,
            'is_description_reviewed'       => $this->is_description_reviewed,
            'is_description_extra_reviewed' => $this->is_description_extra_reviewed,
            'number_current_products'       => $this->number_current_products ?? 0,
            'shop_slug'                     => $this->shop_slug,
            'shop_code'                     => $this->shop_code,
            'shop_name'                     => $this->shop_name,
            'webpage_state'                 => $this->webpage_state,
            'collections'                   => $collections,
            'is_following_master'           => $this->shop_settings ? data_get(json_decode($this->shop_settings), 'catalog.family_follow_master', false) : null,
            'name_updated_at'               => $this->name_updated_at,
            'description_updated_at'        => $this->description_updated_at,
            'description_title_updated_at'  => $this->description_title_updated_at,
            'extra_description_updated_at'  => $this->extra_description_updated_at,
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
