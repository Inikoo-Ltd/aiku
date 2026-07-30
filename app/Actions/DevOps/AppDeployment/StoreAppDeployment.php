<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 29 Jun 2026 22:48:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\DevOps\AppDeployment;

use App\Models\DevOps\AppDeployment;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Symfony\Component\Process\Process;
use Throwable;

class StoreAppDeployment
{
    use AsAction;

    public function getCommandSignature(): string
    {
        return 'deploy:record-deployment {--commit= : The commit hash of the deployment}';
    }

    public function getCommandDescription(): string
    {
        return 'Record a new app deployment in the database';
    }


    public function asCommand(Command $command): int
    {
        $commit = $command->option('commit');

        $appDeployment = AppDeployment::create([
            'commit_hash'      => $commit,
            'semantic_version' => $this->currentTag(base_path()),
        ]);

        if ($commit) {
            try {
                $this->withTimeout(180, fn () => GenerateAppDeploymentChangeLog::run($appDeployment));
            } catch (Throwable $e) {
                Sentry::captureException($e);
            }
        }

        $command->info('Deployment recorded successfully'.($commit ? " for commit $commit" : '').'.');

        return 0;
    }

    private function withTimeout(int $seconds, callable $callback): void
    {
        // pcntl_alarm is only available on the CLI, which is where deployments run.
        if (!function_exists('pcntl_async_signals')) {
            $callback();

            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGALRM, function () use ($seconds) {
            throw new \RuntimeException("Timed out after $seconds seconds");
        });
        pcntl_alarm($seconds);

        try {
            $callback();
        } finally {
            pcntl_alarm(0);
            pcntl_signal(SIGALRM, SIG_DFL);
        }
    }

    private function currentTag(string $path): ?string
    {
        $process = new Process(['git', 'describe', '--tags', '--abbrev=0'], $path);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        return trim($process->getOutput()) ?: null;
    }
}
