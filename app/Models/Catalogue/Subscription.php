<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 23:20:30 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Models\Catalogue;

use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Traits\HasHistory;
use App\Models\Traits\HasUniversalSearch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $asset_id
 * @property bool $status
 * @property ServiceStateEnum $state
 * @property string $slug
 * @property string $code
 * @property string|null $name
 * @property string|null $description
 * @property string|null $price
 * @property string $units
 * @property string $unit
 * @property array<array-key, mixed> $data
 * @property array<array-key, mixed> $settings
 * @property int $currency_id
 * @property int|null $current_historic_asset_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $fetched_at
 * @property string|null $last_fetched_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property-read \App\Models\Catalogue\Asset|null $asset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Helpers\Audit> $audits
 * @property-read \App\Models\Helpers\Currency $currency
 * @property-read \App\Models\SysAdmin\Group $group
 * @property-read \App\Models\Catalogue\HistoricAsset|null $historicAsset
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Catalogue\HistoricAsset> $historicAssets
 * @property-read \App\Models\SysAdmin\Organisation $organisation
 * @property-read \App\Models\Catalogue\Shop|null $shop
 * @property-read \App\Models\Helpers\UniversalSearch|null $universalSearch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Subscription withoutTrashed()
 * @mixin \Eloquent
 */
class Subscription extends Model implements Auditable
{
    use SoftDeletes;
    use HasUniversalSearch;
    use InAssetModel;
    use HasHistory;

    protected $guarded = [];

    protected $casts = [
        'state'                  => ServiceStateEnum::class,
        'status'                 => 'boolean',
        'data'                   => 'array',
        'settings'               => 'array',
    ];

    protected $attributes = [
        'data'     => '{}',
        'settings' => '{}',
    ];


}
