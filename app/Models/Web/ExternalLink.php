<?php

/*
 * author Arya Permana - Kirin
 * created on 18-10-2024-16h-33m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Models\Web;

use App\Models\Traits\InGroup;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property string $url
 * @property int $number_websites_shown
 * @property int $number_webpages_shown
 * @property int $number_web_blocks_shown
 * @property int $number_websites_hidden
 * @property int $number_webpages_hidden
 * @property int $number_web_blocks_hidden
 * @property string|null $status
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\WebBlock> $webBlocks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\Webpage> $webpages
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Web\Website> $websites
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ExternalLink query()
 * @mixin \Eloquent
 */
class ExternalLink extends Model
{
    use InGroup;

    protected $casts = [
    ];

    protected $attributes = [
    ];

    protected $guarded = [];

    public function webBlocks()
    {
        return $this->belongsToMany(WebBlock::class, 'web_block_has_external_link')
                    ->withPivot('website_id', 'webpage_id', 'show')
                    ->withTimestamps();
    }

    public function webpages()
    {
        return $this->belongsToMany(Webpage::class, 'web_block_has_external_link')
                    ->withPivot('website_id', 'web_block_id', 'show')
                    ->withTimestamps();
    }

    public function websites()
    {
        return $this->belongsToMany(Website::class, 'web_block_has_external_link')
                    ->withPivot('webpage_id', 'web_block_id', 'show')
                    ->withTimestamps();
    }
}
