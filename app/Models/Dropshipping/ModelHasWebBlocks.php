<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Aug 2024 11:53:38 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Dropshipping;

use App\Models\Traits\InShop;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
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
 * @property-read WebBlock $webBlock
 * @property-read Webpage|null $webpage
 * @property-read Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasWebBlocks newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasWebBlocks newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ModelHasWebBlocks query()
 * @mixin \Eloquent
 */
class ModelHasWebBlocks extends Model
{
    use InShop;

    protected $table = 'model_has_web_blocks';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array'
    ];

    public function webBlock(): BelongsTo
    {
        return $this->belongsTo(WebBlock::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }



}
