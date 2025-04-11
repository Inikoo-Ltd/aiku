<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 26 Jun 2024 13:46:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Ordering;

use App\Models\Dropshipping\Platform;
use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int|null $organisation_id
 * @property int $shop_id
 * @property int $website_id
 * @property int|null $webpage_id
 * @property int|null $position
 * @property int $web_block_id
 * @property string $model_type
 * @property int $model_id
 * @property bool $show
 * @property bool $show_logged_in
 * @property bool $show_logged_out
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $migration_checksum
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasPlatform newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasPlatform newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasPlatform query()
 * @mixin \Eloquent
 */
class ModelHasPlatform extends Model
{
    use InShop;

    protected $table = 'model_has_platforms';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array'
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }
}
