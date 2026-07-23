<?php

namespace App\Models\CRM;

use App\Models\Traits\InShop;
use App\Models\Web\Webpage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int $shop_id
 * @property int $webpage_id
 * @property int|null $customer_id
 * @property string|null $cookie_id
 * @property \Illuminate\Support\Carbon $last_seen_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Customer|null $customer
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop $shop
 * @property-read Webpage $webpage
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLastSeen newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLastSeen newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ProductLastSeen query()
 * @mixin \Eloquent
 */
class ProductLastSeen extends Model
{
    use InShop;

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    protected $guarded = [];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function webpage(): BelongsTo
    {
        return $this->belongsTo(Webpage::class);
    }
}
