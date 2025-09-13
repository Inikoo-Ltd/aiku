<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 17:27:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $snapshot_id
 * @property int $number_web_blocks
 * @property int $number_menu_columns
 * @property int $number_menu_items
 * @property int $number_columns
 * @property int $number_header_columns
 * @property int $number_footer_columns
 * @property int|null $height_desktop
 * @property int|null $height_mobile
 * @property int $number_internal_links
 * @property int $number_external_links
 * @property int $number_images
 * @property int $filesize
 * @property int $number_slides for banners
 * @property int $number_rows for emails
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Helpers\Snapshot $snapshot
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SnapshotStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SnapshotStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SnapshotStats query()
 * @mixin \Eloquent
 */
class SnapshotStats extends Model
{
    protected $guarded = [];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class);
    }
}
