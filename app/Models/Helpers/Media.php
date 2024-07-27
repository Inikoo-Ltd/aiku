<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:34:39 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use App\Helpers\ImgProxy\Image;
use App\Models\Traits\IsMedia;
use Illuminate\Database\Query\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media as BaseMedia;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property string $slug
 * @property string $type
 * @property string|null $model_type
 * @property int|null $model_id
 * @property string|null $uuid
 * @property string $collection_name
 * @property string $name
 * @property string $file_name
 * @property string|null $mime_type
 * @property string $disk
 * @property string|null $conversions_disk
 * @property int $size
 * @property array $manipulations
 * @property array $custom_properties
 * @property array $generated_conversions
 * @property array $responsive_images
 * @property string $ulid
 * @property string|null $checksum
 * @property int $multiplicity
 * @property int $usage
 * @property bool $is_animated
 * @property int|null $order_column
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $extension
 * @property-read mixed $human_readable_size
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent|null $model
 * @property-read mixed $original_url
 * @property-read mixed $preview_url
 * @method static \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, static> all($columns = ['*'])
 * @method static \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, static> get($columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Builder|Media newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Media newQuery()
 * @method static Builder|Media ordered()
 * @method static \Illuminate\Database\Eloquent\Builder|Media query()
 * @mixin \Eloquent
 */
class Media extends BaseMedia
{
    use IsMedia;

    protected $table = 'media';

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getImage(): Image
    {
        return (new Image())->make($this->getImgProxyFilename(), $this->is_animated);
    }

}
