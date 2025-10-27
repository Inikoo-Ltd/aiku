<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 08 May 2025 15:29:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\PlatformLogs\PlatformPortfolioLogsTypeEnum;
use Illuminate\Support\Facades\DB;

class DeleteUnusedPortfolioLogs
{
    use WithActionUpdate;


    protected function handle(): void
    {
        DB::table('platform_portfolio_logs')
            ->whereIn('type', [PlatformPortfolioLogsTypeEnum::UPLOAD, PlatformPortfolioLogsTypeEnum::MATCH])
            ->where('created_at', '<=', now()->subDays(5))->delete();

        DB::table('platform_portfolio_logs')
            ->where('type', PlatformPortfolioLogsTypeEnum::UPDATE_STOCK)
            ->where('created_at', '<=', now()->subDays(2))->delete();
    }

    public string $commandSignature = 'platform-logs:delete';

    public function asCommand(): void
    {
        $this->handle();
    }
}
