<?php

namespace App\Models\Chat;

use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $meta_channel_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int $meta_chat_agent_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property string|null $delete_comment
 */
class ShopHasMetaChatAgent extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'shop_has_meta_chat_agents';

    protected $fillable = [
        'meta_channel_id',
        'organisation_id',
        'shop_id',
        'meta_chat_agent_id',
    ];

    protected $casts = [
        'shop_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function metaChannel(): BelongsTo
    {
        return $this->belongsTo(MetaChannel::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(MetaChatAgent::class, 'meta_chat_agent_id');
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
