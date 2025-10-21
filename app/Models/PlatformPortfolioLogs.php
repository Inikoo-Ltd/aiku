<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 15 Oct 2025 14:48:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Models;

use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsStatusEnum;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $group_id
 * @property int $organisation_id
 * @property int|null $shop_id
 * @property int|null $customer_id
 * @property int $customer_sales_channel_id
 * @property int|null $portfolio_id
 * @property int $platform_id
 * @property string $platform_type
 * @property PlatformPortfolioLogsTypeEnum $type
 * @property PlatformPortfolioLogsStatusEnum $status
 * @property string|null $response
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformPortfolioLogs newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformPortfolioLogs newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PlatformPortfolioLogs query()
 * @mixin \Eloquent
 */
class PlatformPortfolioLogs extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => PlatformPortfolioLogsStatusEnum::class,
        'type' => PlatformPortfolioLogsTypeEnum::class,
    ];
}
