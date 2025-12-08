<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 01 Jan 2025 04:31:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Utils;

use Lorisleiva\Actions\Concerns\AsCommand;

class ClearLogs
{
    use AsCommand;

    public string $commandSignature = 'logs:clear';

    public string $commandDescription = 'Clear the log files';

    public function handle(): void
    {
        $logFiles = glob(storage_path('logs/*.log'));
        foreach ($logFiles as $logFile) {
            unlink($logFile);
        }
    }
}
