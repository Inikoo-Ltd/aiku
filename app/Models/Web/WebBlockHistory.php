<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 13 Jul 2023 14:24:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models\Web;

use App\Models\Traits\InShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 *
 *
 * @property int $id
 * @property int $group_id
 * @property int $website_id
 * @property int $webpage_id
 * @property int|null $web_block_id
 * @property int $web_block_type_id
 * @property string|null $checksum
 * @property object $layout
 * @property object $data
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation|null $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Web\WebBlock|null $webBlock
 * @property-read \App\Models\Web\WebBlockType $webBlockType
 * @property-read \App\Models\Web\Webpage $webpage
 * @property-read \App\Models\Web\Website $website
 * @method static Builder<static>|WebBlockHistory newModelQuery()
 * @method static Builder<static>|WebBlockHistory newQuery()
 * @method static Builder<static>|WebBlockHistory query()
 * @mixin \Eloquent
 */
class WebBlockHistory extends Model
{
    use InShop;

    protected $casts = [
        'layout' => 'object',
        'data'  => 'object',
    ];

    protected $attributes = [
        'layout' => '{}',
        'data'   => '{}',
    ];

    protected $guarded = [];


    public function webBlockType(): BelongsTo
    {
        return $this->belongsTo(WebBlockType::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }

    public function webBlock(): BelongsTo
    {
        return $this->belongsTo(WebBlock::class);
    }
}
