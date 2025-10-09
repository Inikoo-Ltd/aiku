<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 13:39:24 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property string $slug
 * @property int $org_partner_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property string|null $source_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|StockTransfer query()
 * @mixin \Eloquent
 */
class StockTransfer extends Model
{
}
