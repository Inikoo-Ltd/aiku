<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 19:30:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\SysAdmin;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $number_organisations
 * @property int $number_organisations_type_shop
 * @property int $number_organisations_type_agent
 * @property int $number_organisations_type_digital_agency
 * @property int $number_images
 * @property int $filesize_images
 * @property int $number_attachments
 * @property int $filesize_attachments
 * @property int $number_uploads
 * @property int $number_upload_records
 * @property int $number_queries
 * @property int $number_static_queries is_static=true
 * @property int $number_dynamic_queries is_static=false
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|GroupStats query()
 * @mixin \Eloquent
 */
class GroupStats extends Model
{
    protected $table = 'group_stats';

    protected $guarded = [];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
