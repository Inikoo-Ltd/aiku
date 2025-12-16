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
 * @property string|null $filename
 * @property string $path
 * @property int $file_size
 * @property string|null $content
 * @property string|null $checksum
 * @property bool $is_active
 * @property bool $use_fallback
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $uploaded_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Group $group
 * @property-read Organisation $organisation
 * @property-read Website $website
 * @property-read User|null $uploader
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
