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

class PlatformPortfolioLogs extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => PlatformPortfolioLogsStatusEnum::class,
        'type' => PlatformPortfolioLogsTypeEnum::class,
    ];
}
