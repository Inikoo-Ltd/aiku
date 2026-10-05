<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Oct 2026 19:05:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\CiRun;

use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportGitHubWorkflowRuns
{
    use AsAction;

    public string $commandSignature = 'ci-runs:import {--runs=20 : How many recent workflow runs to import}';

    public function handle(int $runs = 20): int
    {
        $repo = config('services.github.repo');

        $workflowRuns = $this->github()->get("https://api.github.com/repos/$repo/actions/runs", ['per_page' => min($runs, 100)])->throw()->json('workflow_runs', []);

        foreach ($workflowRuns as $workflowRun) {
            ReceiveGitHubWorkflowWebhook::run('workflow_run', ['workflow_run' => $workflowRun])?->update(['jobs' => []]);

            $jobs = $this->github()->get("https://api.github.com/repos/$repo/actions/runs/{$workflowRun['id']}/jobs", ['per_page' => 50])->throw()->json('jobs', []);
            foreach ($jobs as $job) {
                ReceiveGitHubWorkflowWebhook::run('workflow_job', ['workflow_job' => $job]);
            }
        }

        return count($workflowRuns);
    }

    private function github(): PendingRequest
    {
        $request = Http::connectTimeout(5)->timeout(20)->acceptJson();

        return config('services.github.token') ? $request->withToken(config('services.github.token')) : $request;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $command->info('Imported '.$this->handle((int) $command->option('runs')).' workflow runs.');

        return 0;
    }
}
