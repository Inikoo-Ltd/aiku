<?php

/*
 * author Arya Permana - Kirin
 * created on 07-07-2025-18h-09m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Models\Inventory;

use App\Models\Dispatching\Picking;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class PickingIssue extends Model
{
    use HasFactory;
    use HasSlug;

    protected $guarded = [];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('reference')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }


    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function model(): MorphTo 
    {
        return $this->morphTo();
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issuer_user_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->BelongsTo(User::class, 'resolver_user_id');
    }

    public function picking(): BelongsTo 
    {
        return $this->belongsTo(Picking::class);
    }

    public function orgStock(): BelongsTo
    {
        return $this->belongsTo(OrgStock::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }  

    public function messages(): HasMany
    {
        return $this->hasMany(PickingIssueMessage::class);
    }  

}
