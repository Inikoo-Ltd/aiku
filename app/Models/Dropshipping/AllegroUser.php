<?php

namespace App\Models\Dropshipping;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroApiServices;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $allegro_id
 * @property int $customer_id
 * @property int $customer_sales_channel_id
 * @property bool $status
 * @property string $name
 * @property string|null $email
 * @property string|null $username
 * @property string|null $marketplace_id
 * @property string|null $access_token
 * @property string|null $access_token_expire_in
 * @property string|null $refresh_token
 * @property string|null $refresh_token_expire_in
 * @property string $auth_type
 * @property string $state
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property string|null $deleted_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Dropshipping\CustomerSalesChannel $customerSalesChannel
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AllegroUser query()
 * @mixin \Eloquent
 */
class AllegroUser extends Model
{
    use WithAllegroApiServices;

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'settings' => 'array',
    ];

    protected $attributes = [
        'data' => '{}',
        'settings' => '{}',
    ];

    public function customerSalesChannel(): BelongsTo
    {
        return $this->belongsTo(CustomerSalesChannel::class);
    }
}
