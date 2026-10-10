<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 18:17:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\CiRun;

use App\Events\BroadcastCiRunUpdated;
use App\Models\DevOps\CiRun;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ReceiveGitHubWorkflowWebhook
{
    use AsAction;

    public function handle(string $event, array $payload): ?CiRun
    {
        $ciRun = match ($event) {
            'workflow_run' => $this->storeRun($payload['workflow_run']),
            'workflow_job' => $this->storeJob($payload['workflow_job']),
            default        => null,
        };

        if ($ciRun) {
            BroadcastCiRunUpdated::dispatch($ciRun);
        }

        return $ciRun;
    }

    private function storeRun(array $run): CiRun
    {
        return CiRun::updateOrCreate(['github_run_id' => $run['id']], [
            'run_attempt'  => $run['run_attempt'] ?? 1,
            'workflow'     => $run['name'] ?? null,
            'branch'       => $run['head_branch'] ?? null,
            'head_sha'     => $run['head_sha'] ?? null,
            'head_message' => Arr::get($run, 'head_commit.message'),
            'actor'        => Arr::get($run, 'actor.login'),
            'status'       => $run['status'] ?? null,
            'conclusion'   => $run['conclusion'] ?? null,
            'html_url'     => $run['html_url'] ?? null,
            'started_at'   => $run['run_started_at'] ?? null,
            'completed_at' => ($run['status'] ?? null) === 'completed' ? ($run['updated_at'] ?? now()) : null,
        ]);
    }

    private function storeJob(array $job): CiRun
    {
        return DB::transaction(function () use ($job) {
            CiRun::firstOrCreate(['github_run_id' => $job['run_id']], ['workflow' => $job['workflow_name'] ?? null, 'branch' => $job['head_branch'] ?? null, 'head_sha' => $job['head_sha'] ?? null]);

            $ciRun = CiRun::where('github_run_id', $job['run_id'])->lockForUpdate()->firstOrFail();

            $jobs             = $ciRun->jobs;
            $jobs[$job['id']] = [
                'name'         => $job['name'],
                'status'       => $job['status'],
                'conclusion'   => $job['conclusion'] ?? null,
                'started_at'   => $job['started_at'] ?? null,
                'completed_at' => $job['completed_at'] ?? null,
                'html_url'     => $job['html_url'] ?? null,
                'steps'        => array_map(fn (array $step) => Arr::only($step, ['number', 'name', 'status', 'conclusion', 'started_at', 'completed_at']), $job['steps'] ?? []),
            ];
            $ciRun->update(['jobs' => $jobs]);

            return $ciRun;
        });
    }

    public function isValidSignature(Request $request): bool
    {
        $secret = config('services.github.webhook_secret');

        return $secret && hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), (string) $request->header('X-Hub-Signature-256'));
    }

    public function asController(Request $request): array
    {
        abort_unless($this->isValidSignature($request), 403);

        $this->handle((string) $request->header('X-GitHub-Event'), $request->json()->all());

        return ['ok' => true];
    }
}
