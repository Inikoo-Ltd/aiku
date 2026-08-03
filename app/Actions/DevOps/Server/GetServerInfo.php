<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 03 Jun 2026 11:13:34 Indochina Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\Server;

use App\Models\DevOps\Server;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetServerInfo
{
    use AsAction;

    public function handle(Server $server): array
    {
        $loadAverages = sys_getloadavg();

        // To get an approximate current percentage, you can divide the 1-min load by the number of CPU cores
        $cores = (int)shell_exec('nproc');
        if ($cores === 0) {
            $currentCpuUsage = 'Unknown';
        } else {
            $currentCpuUsage = ($loadAverages[0] / $cores) * 100;
        }

        list($totalGB, $usedGB, $percentage) = $this->getMemoryData();

        return [
            'slug'      => $server->slug,
            'ip'        => gethostbyname(php_uname('n')),
            'name'      => gethostname(),
            'load'      => $loadAverages,
            'cpu_usage' => $currentCpuUsage,
            'memory'    => [
                'total'      => $totalGB,
                'used'       => $usedGB,
                'percentage' => $percentage,
            ],
        ];
    }

    public function getMemoryData(): array
    {
        if (!file_exists('/proc/meminfo')) {
            return [0, 0, 0];
        }

        // Read mem info line by line
        $memInfo = file('/proc/meminfo');
        $stats   = [];

        foreach ($memInfo as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $matches)) {
                $stats[$matches[1]] = (int)$matches[2]; // Values are in kB
            }
        }

        // Extract key metrics
        $totalRaw   = $stats['MemTotal'] ?? 0;
        $freeRaw    = $stats['MemFree'] ?? 0;
        $buffersRaw = $stats['Buffers'] ?? 0;
        $cachedRaw  = $stats['Cached'] ?? 0;

        // Accurate Linux calculation: Used = Total-Free - Buffers - Cached
        $usedRaw = $totalRaw - ($freeRaw + $buffersRaw + $cachedRaw);

        // Convert to Gigabytes for better readability
        $totalGB    = round($totalRaw / 1024 / 1024, 2);
        $usedGB     = round($usedRaw / 1024 / 1024, 2);
        $percentage = $totalRaw > 0 ? round(($usedRaw / $totalRaw) * 100, 2) : 0;


        return [$totalGB, $usedGB, $percentage];
    }

    public function asController(Server $server, ActionRequest $request): \Illuminate\Http\Response|array
    {

        return $this->handle($server);
    }

}
