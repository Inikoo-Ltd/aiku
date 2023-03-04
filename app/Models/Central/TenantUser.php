<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Tue, 20 Sept 2022 19:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;


/**
 * App\Models\Central\TenantUser
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property bool $status
 * @property-read \App\Models\Central\Tenant $tenant
 * @method static Builder|TenantUser newModelQuery()
 * @method static Builder|TenantUser newQuery()
 * @method static Builder|TenantUser query()
 * @method static Builder|TenantUser whereId($value)
 * @method static Builder|TenantUser whereStatus($value)
 * @method static Builder|TenantUser whereTenantId($value)
 * @method static Builder|TenantUser whereUserId($value)
 * @mixin \Eloquent
 */
class TenantUser extends Pivot
{
    use UsesLandlordConnection;

    public $incrementing = true;

    public $table='tenant_users';



    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

}
