<?php

namespace App\Models\Chat;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 */
class MetaChannel extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'meta_channels';

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function agents(): HasMany
    {
        return $this->hasMany(MetaChatAgent::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MetaChatSession::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MetaChatMessage::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MetaChatAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(MetaChatEvent::class);
    }

    public function shopAgents(): HasMany
    {
        return $this->hasMany(ShopHasMetaChatAgent::class);
    }
}
