<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 19:51:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models\SupplyChain;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $supplier_user_id
 * @property int $number_logins
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $last_active_at
 * @property int $number_failed_logins
 * @property string|null $last_failed_login_ip
 * @property string|null $last_failed_login_at
 * @property int $number_audits
 * @property int $number_audits_event_created
 * @property int $number_audits_event_updated
 * @property int $number_audits_event_deleted
 * @property int $number_audits_event_restored
 * @property int $number_audits_event_customer_note
 * @property int $number_audits_event_other
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\SupplyChain\SupplierUser $supplierUser
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUserStats newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUserStats newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SupplierUserStats query()
 * @mixin \Eloquent
 */
class SupplierUserStats extends Model
{
    protected $table = 'supplier_user_stats';

    protected $guarded = [];

    protected $casts = [
        'last_location' => 'array',
    ];

    protected $attributes = [
        'last_location' => '{}',
    ];

    public function supplierUser(): BelongsTo
    {
        return $this->belongsTo(SupplierUser::class);
    }
}
