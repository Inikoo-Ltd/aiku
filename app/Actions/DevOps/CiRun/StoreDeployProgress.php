<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\CiRun;

use App\Events\BroadcastCiRunUpdated;
use App\Models\DevOps\CiRun;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreDeployProgress
{
    use AsAction;

    public function handle(array $modelData): CiRun
    {
        $ciRun = DB::transaction(function () use ($modelData) {
            CiRun::firstOrCreate(['github_run_id' => $modelData['run_id']], ['workflow' => 'Deploy Aiku']);

            $ciRun = CiRun::where('github_run_id', $modelData['run_id'])->lockForUpdate()->firstOrFail();

            $ciRun->update([
                'deploy_tasks' => [
                    ...$ciRun->deploy_tasks,
                    [
                        'task'  => $modelData['task'],
                        'host'  => $modelData['host'] ?? null,
                        'state' => $modelData['state'],
                        'index' => $modelData['index'] ?? null,
                        'total' => $modelData['total'] ?? null,
                        'at'    => now()->toIso8601String(),
                    ],
                ],
            ]);

            return $ciRun;
        });

        BroadcastCiRunUpdated::dispatch($ciRun->github_run_id);

        return $ciRun;
    }

    public function rules(): array
    {
        return [
            'run_id' => ['required', 'integer', 'min:1'],
            'task'   => ['required', 'string', 'max:100'],
            'host'   => ['nullable', 'string', 'max:50'],
            'state'  => ['required', 'in:start,done,failed'],
            'index'  => ['nullable', 'integer', 'min:1'],
            'total'  => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->validated());

        return ['ok' => true];
    }
}
