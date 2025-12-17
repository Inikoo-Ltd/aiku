<?php

namespace App\Models\Web;

use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * App\Models\Web\WebsiteLlmsTxt
 *
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $website_id
 * @property string|null $filename Original filename
 * @property string $path Storage path
 * @property int $file_size Size in bytes
 * @property string|null $content File content for quick serving
 * @property string|null $checksum MD5 checksum for integrity
 * @property bool $is_active
 * @property bool $use_fallback Use global fallback if no file
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $uploaded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read User|null $uploader
 * @property-read \App\Models\Web\Website $website
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteLlmsTxt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteLlmsTxt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteLlmsTxt query()
 * @mixin \Eloquent
 */
class WebsiteLlmsTxt extends Model
{
    protected $table = 'website_llms_txt';

    protected $guarded = [];

    protected $casts = [
        'is_active'    => 'boolean',
        'use_fallback' => 'boolean',
        'uploaded_at'  => 'datetime',
    ];

    protected $attributes = [
        'is_active'    => true,
        'use_fallback' => true,
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public static function getActiveForWebsite(Website $website): ?self
    {
        return static::where('website_id', $website->id)
            ->where('is_active', true)
            ->latest()
            ->first();
    }
}
