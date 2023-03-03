<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 17 Oct 2022 18:13:18 British Summer Time, Sheffield, UK
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * App\Models\Central\TenantMarketingStats
 *
 * @property int $id
 * @property string $tenant_id
 * @property int $number_shops
 * @property int $number_shops_state_in_process
 * @property int $number_shops_state_open
 * @property int $number_shops_state_closing_down
 * @property int $number_shops_state_closed
 * @property int $number_shops_type_shop
 * @property int $number_shops_type_fulfilment_house
 * @property int $number_shops_type_agent
 * @property int $number_shops_subtype_b2b
 * @property int $number_shops_subtype_b2c
 * @property int $number_shops_subtype_storage
 * @property int $number_shops_subtype_fulfilment
 * @property int $number_shops_subtype_dropshipping
 * @property int $number_shops_state_subtype_in_process_b2b
 * @property int $number_shops_state_subtype_in_process_b2c
 * @property int $number_shops_state_subtype_in_process_storage
 * @property int $number_shops_state_subtype_in_process_fulfilment
 * @property int $number_shops_state_subtype_in_process_dropshipping
 * @property int $number_shops_state_subtype_open_b2b
 * @property int $number_shops_state_subtype_open_b2c
 * @property int $number_shops_state_subtype_open_storage
 * @property int $number_shops_state_subtype_open_fulfilment
 * @property int $number_shops_state_subtype_open_dropshipping
 * @property int $number_shops_state_subtype_closing_down_b2b
 * @property int $number_shops_state_subtype_closing_down_b2c
 * @property int $number_shops_state_subtype_closing_down_storage
 * @property int $number_shops_state_subtype_closing_down_fulfilment
 * @property int $number_shops_state_subtype_closing_down_dropshipping
 * @property int $number_shops_state_subtype_closed_b2b
 * @property int $number_shops_state_subtype_closed_b2c
 * @property int $number_shops_state_subtype_closed_storage
 * @property int $number_shops_state_subtype_closed_fulfilment
 * @property int $number_shops_state_subtype_closed_dropshipping
 * @property int $number_orphan_families
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static Builder|TenantMarketingStats newModelQuery()
 * @method static Builder|TenantMarketingStats newQuery()
 * @method static Builder|TenantMarketingStats query()
 * @method static Builder|TenantMarketingStats whereCreatedAt($value)
 * @method static Builder|TenantMarketingStats whereId($value)
 * @method static Builder|TenantMarketingStats whereNumberOrphanFamilies($value)
 * @method static Builder|TenantMarketingStats whereNumberShops($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateClosed($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateClosingDown($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateInProcess($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateOpen($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosedB2b($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosedB2c($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosedDropshipping($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosedFulfilment($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosedStorage($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosingDownB2b($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosingDownB2c($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosingDownDropshipping($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosingDownFulfilment($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeClosingDownStorage($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeInProcessB2b($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeInProcessB2c($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeInProcessDropshipping($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeInProcessFulfilment($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeInProcessStorage($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeOpenB2b($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeOpenB2c($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeOpenDropshipping($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeOpenFulfilment($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsStateSubtypeOpenStorage($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsSubtypeB2b($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsSubtypeB2c($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsSubtypeDropshipping($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsSubtypeFulfilment($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsSubtypeStorage($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsTypeAgent($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsTypeFulfilmentHouse($value)
 * @method static Builder|TenantMarketingStats whereNumberShopsTypeShop($value)
 * @method static Builder|TenantMarketingStats whereTenantId($value)
 * @method static Builder|TenantMarketingStats whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TenantMarketingStats extends Model
{
    use UsesLandlordConnection;

    protected $table = 'tenant_marketing_stats';

    protected $guarded = [];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
