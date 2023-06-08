<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Jun 2023 00:40:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Models;

use App\Models\Tenancy\Tenant;
use App\Models\Traits\UsesGroupConnection;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * App\Models\TenantWebStats
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $number_websites
 * @property int $number_webpages
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @method static Builder|TenantWebStats newModelQuery()
 * @method static Builder|TenantWebStats newQuery()
 * @method static Builder|TenantWebStats query()
 * @mixin Eloquent
 */
class TenantWebStats extends Model
{
    use UsesGroupConnection;

    protected $table = 'tenant_web_stats';

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
