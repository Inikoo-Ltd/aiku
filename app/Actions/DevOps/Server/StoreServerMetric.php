<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 03:07:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\Server;

use App\Models\DevOps\Server;
use App\Models\DevOps\ServerMetric;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreServerMetric
{
    use AsAction;

    public function handle(Server $server, array $modelData): ServerMetric
    {
        $disks = $modelData['disks'] ?? [];

        return $server->hasMany(ServerMetric::class)->create([
            ...Arr::except($modelData, ['disks']),
            'recorded_at'  => now(),
            'disk_percent'  => $disks ? max(array_column($disks, 'percent')) : 0,
            'inode_percent' => $disks ? max(array_map(fn (array $disk) => $disk['inode_percent'] ?? 0, $disks)) : null,
            'disks'        => $disks,
        ]);
    }

    public function rules(): array
    {
        return [
            'cpu_percent'      => ['required', 'numeric', 'between:0,100'],
            'memory_percent'   => ['required', 'numeric', 'between:0,100'],
            'swap_percent'     => ['nullable', 'numeric', 'between:0,100'],
            'load_1'           => ['nullable', 'numeric', 'min:0'],
            'cpu_cores'        => ['nullable', 'integer', 'min:1'],
            'memory_total_mb'  => ['nullable', 'integer', 'min:0'],
            'iowait_percent'   => ['nullable', 'numeric', 'between:0,100'],
            'net_rx_mbps'      => ['nullable', 'numeric', 'min:0'],
            'net_tx_mbps'      => ['nullable', 'numeric', 'min:0'],
            'disk_read_mbps'   => ['nullable', 'numeric', 'min:0'],
            'disk_write_mbps'  => ['nullable', 'numeric', 'min:0'],
            'processes'        => ['nullable', 'integer', 'min:0'],
            'tcp_connections'  => ['nullable', 'integer', 'min:0'],
            'disks'            => ['present', 'array', 'max:20'],
            'disks.*.mount'    => ['required', 'string', 'max:255'],
            'disks.*.percent'  => ['required', 'numeric', 'between:0,100'],
            'disks.*.size_gb'  => ['nullable', 'numeric', 'min:0'],
            'disks.*.inode_percent' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function asController(string $serverSlug, ActionRequest $request): array
    {
        abort_unless(preg_match('/^[a-z0-9-]{1,32}$/', $serverSlug), 422);

        $server = Server::firstOrCreate(['slug' => $serverSlug], ['name' => $serverSlug, 'ip' => $request->ip()]);

        $this->handle($server, $request->validated());

        return ['ok' => true];
    }
}
